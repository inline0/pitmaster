import assert from "node:assert/strict";
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync, mkdirSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { spawnSync } from "node:child_process";
import { test } from "node:test";
import * as Effect from "effect/Effect";
import { runtimeLayer } from "./lib/cli.ts";
import * as Command from "./lib/command.ts";
import { auditDocumentation, loadDocumentation } from "./check-docs-content.ts";
import { checks, verify } from "./verify.ts";

const live = <A, E>(effect: Effect.Effect<A, E, Command.Command>) =>
    Effect.runPromise(effect.pipe(Effect.provide(runtimeLayer({ json: false, dryRun: false }))));
const document = (body = "Hello.", extra = "") => `---\ntitle: Test\ndescription: Test\npath: /test\norder: 0\nmeta_title: Test\nmeta_description: Test\n${extra}---\n${body}\n`;

test("the live documentation loader preserves navigation, frontmatter and link validation", async () => {
    const root = mkdtempSync(join(tmpdir(), "inline0-docs-"));
    try {
        writeFileSync(join(root, "index.md"), document("[Asset](asset.txt)\n```tsx\n<Component />\n```"));
        writeFileSync(join(root, "asset.txt"), "asset");
        writeFileSync(join(root, "meta.json"), '{"pages":["index"]}');
        const tree = await Effect.runPromise(loadDocumentation(root).pipe(Effect.provide(runtimeLayer({ json: false, dryRun: false }))));
        assert.deepEqual(auditDocumentation(root, tree, 1, 1).problems, []);
        writeFileSync(join(root, "index.md"), document("import module\n<Component />\n[Missing](missing.md)\n[Malformed](bad%zz)", "title: Duplicate\n"));
        writeFileSync(join(root, "orphan.md"), document());
        writeFileSync(join(root, "legacy.mdx"), "legacy");
        mkdirSync(join(root, "other"));
        writeFileSync(join(root, "other/meta.json"), '{}');
        const changed = await Effect.runPromise(loadDocumentation(root).pipe(Effect.provide(runtimeLayer({ json: false, dryRun: false }))));
        const problems = auditDocumentation(root, changed, 1, 1).problems.join("\n");
        for (const expected of ["duplicate frontmatter", "duplicate path", "duplicate order", "module syntax", "executable JSX", "broken relative link", "unreachable", "canonical content must be .md", "expected 1 Markdown", "expected 1 navigation"]) assert.ok(problems.includes(expected), expected);
        writeFileSync(join(root, "meta.json"), '{invalid');
        const invalid = await Effect.runPromise(loadDocumentation(root).pipe(Effect.provide(runtimeLayer({ json: false, dryRun: false }))));
        assert.match(auditDocumentation(root, invalid).problems.join("\n"), /invalid JSON/);
    } finally { rmSync(root, { recursive: true, force: true }); }
});

test("Command captures both pipes concurrently, inherits cwd/env, and preserves child failures", async () => {
    const result = await live(Command.run({ command: process.execPath, args: ["-e", 'process.stdout.write(process.cwd()+"|"+process.env.PROBE);process.stderr.write("warn");process.exit(7)'], cwd: tmpdir(), env: { PROBE: "test" }, check: false }));
    assert.equal(result.exitCode, 7);
    assert.ok(result.stdout.endsWith("|test"));
    assert.equal(result.stderr, "warn");
    const failed = await live(Command.run({ command: process.execPath, args: ["-e", "process.exit(9)"] }).pipe(Effect.flip));
    assert.equal(failed.exitCode, 9);
    const large = await live(Command.run({ command: process.execPath, args: ["-e", 'process.stdout.write("x".repeat(200000));process.stderr.write("y".repeat(200000))'] }));
    assert.equal(large.stdout.length, 200000);
    assert.equal(large.stderr.length, 200000);
});

test("interruption waits for a scoped child to die, including one ignoring SIGTERM", async () => {
    const root = mkdtempSync(join(tmpdir(), "inline0-child-"));
    const pidfile = join(root, "pid");
    try {
        await live(Command.run({ command: process.execPath, args: ["-e", `require('node:fs').writeFileSync(${JSON.stringify(pidfile)},String(process.pid));process.on('SIGTERM',()=>{});setInterval(()=>{},1000)`] }).pipe(Effect.timeout("500 millis"), Effect.catchCause(() => Effect.void)));
        assert.ok(existsSync(pidfile));
        const pid = Number(readFileSync(pidfile, "utf8"));
        assert.throws(() => process.kill(pid, 0), { code: "ESRCH" });
    } finally { rmSync(root, { recursive: true, force: true }); }
});

test("verification stops at the first failed check and uses the requested checkout", async () => {
    const seen: Command.CommandCall[] = [];
    const failure = await Effect.runPromise(verify({ json: true, dryRun: false }, "/checkout").pipe(
        Effect.provideService(Command.Command, { run: (call) => { seen.push(call); return Effect.fail(new Command.CommandFailed({ exitCode: 6, message: "fixture" })); } }), Effect.flip,
    ));
    assert.equal(failure.exitCode, 6);
    assert.equal(seen.length, 1);
    assert.equal(seen[0].cwd, "/checkout");
    assert.equal(seen[0].command, checks[0].command);
});

test("CLI help/usage, failure status, and verification preview are stable", () => {
    const call = (file: string, args: string[]) => spawnSync(process.execPath, [file, ...args], { encoding: "utf8", timeout: 10000 });
    const help = call("scripts/verify.ts", ["--help"]);
    assert.equal(help.status, 0, help.stderr);
    for (const flag of ["--json", "--dry-run", "--log-level"]) assert.ok(help.stdout.includes(flag));
    assert.equal(call("scripts/verify.ts", ["--unknown"]).status, 2);
    assert.equal(call("scripts/check-docs-content.ts", ["--root"]).status, 2);
    assert.equal(call("scripts/check-docs-content.ts", ["--root", "/nonexistent/inline0"]).status, 1);
    const preview = call("scripts/verify.ts", ["--dry-run", "--json"]);
    assert.equal(preview.status, 0, preview.stderr);
    assert.equal(JSON.parse(preview.stdout).checks, checks.length);
    assert.equal(preview.stderr.split("\n").filter((line) => line.includes('"dryRun":true')).length, checks.length);
});

test("JSON child output is structured stderr and previews redact environment values", () => {
    const call = (args: string[]) => spawnSync(process.execPath, ["scripts/fixtures/command-fixture.ts", ...args], { encoding: "utf8", timeout: 10000 });
    const result = call(["--json"]);
    assert.equal(result.status, 0, result.stderr);
    assert.deepEqual(JSON.parse(result.stdout), { success: true });
    assert.match(result.stderr, /child output/);
    assert.ok(result.stderr.trim().split("\n").every((line) => JSON.parse(line).text));
    assert.equal(call(["--code", "7"]).status, 7);
    const preview = call(["--dry-run", "--json"]);
    assert.equal(preview.status, 0, preview.stderr);
    assert.ok(!preview.stderr.includes("never-log-this"));
});
