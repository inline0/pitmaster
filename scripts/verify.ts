import { fileURLToPath } from "node:url";
import * as Console from "effect/Console";
import * as Effect from "effect/Effect";
import { main, script, type ScriptInput } from "./lib/cli.ts";
import * as Command from "./lib/command.ts";

export const root = fileURLToPath(new URL("../", import.meta.url));
export const checks = [
    { "name": "TypeScript tooling", "command": "composer", "args": ["run-script", "verify:tooling", "--no-interaction"] },
    {
        "name": "verify:evidence",
        "command": "composer",
        "args": [
            "run-script",
            "verify:evidence",
            "--no-interaction"
        ]
    },
    {
        "name": "verify:drift",
        "command": "composer",
        "args": [
            "run-script",
            "verify:drift",
            "--no-interaction"
        ]
    },
    {
        "name": "verify:proof",
        "command": "composer",
        "args": [
            "run-script",
            "verify:proof",
            "--no-interaction"
        ]
    },
    {
        "name": "test:mutation",
        "command": "composer",
        "args": [
            "run-script",
            "test:mutation",
            "--no-interaction"
        ]
    },
    {
        "name": "audit:composer",
        "command": "composer",
        "args": [
            "run-script",
            "audit:composer",
            "--no-interaction"
        ]
    },
    {
        "name": "analyse",
        "command": "composer",
        "args": [
            "run-script",
            "analyse",
            "--no-interaction"
        ]
    },
    {
        "name": "cs",
        "command": "composer",
        "args": [
            "run-script",
            "cs",
            "--no-interaction"
        ]
    },
    {
        "name": "test",
        "command": "composer",
        "args": [
            "run-script",
            "test",
            "--no-interaction"
        ]
    }
] as const;

export const verify = (input: ScriptInput, directory = root) => Effect.gen(function* () {
    for (const check of checks) {
        yield* Console.error(input.json ? JSON.stringify({ check: check.name, status: "running" }) : `=== ${check.name} ===`);
        yield* Command.run({ command: check.command, args: check.args, cwd: directory, inherit: true });
    }
    yield* Console.log(input.json ? JSON.stringify({ checks: checks.length, success: true, dryRun: input.dryRun }) : "All checks passed.");
});
export const command = script("verify", {
    description: "Run the complete repository verification sequence, stopping at the first failure.",
    flags: {}, run: verify,
});
if (import.meta.main) main(command);
