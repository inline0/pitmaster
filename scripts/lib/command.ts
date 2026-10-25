import * as Context from "effect/Context";
import * as Data from "effect/Data";
import * as Effect from "effect/Effect";
import * as Layer from "effect/Layer";
import * as Runtime from "effect/Runtime";
import * as ChildProcess from "effect/process/ChildProcess";
import * as ChildProcessSpawner from "effect/process/ChildProcessSpawner";
import * as Stream from "effect/Stream";

export interface CommandCall {
    readonly command: string;
    readonly args?: ReadonlyArray<string>;
    readonly cwd?: string;
    readonly env?: Readonly<Record<string, string>>;
    readonly inherit?: boolean;
    readonly check?: boolean;
    readonly mutates?: boolean;
    readonly onOutput?: (text: string, channel: "stdout" | "stderr") => Effect.Effect<void>;
}

export interface CommandResult {
    readonly exitCode: number;
    readonly stdout: string;
    readonly stderr: string;
}

export class CommandFailed extends Data.TaggedError("CommandFailed")<{
    readonly message: string;
    readonly exitCode: number;
}> {
    override get [Runtime.errorExitCode]() { return this.exitCode; }
}

export class Command extends Context.Service<Command, {
    readonly run: (call: CommandCall) => Effect.Effect<CommandResult, CommandFailed>;
}>()("inline0/scripts/Command") {}

export const describe = (call: CommandCall) =>
    [call.command, ...(call.args ?? [])].map((word) => JSON.stringify(word)).join(" ");

// The spawner owns each child in a scope, including its output streams. Failure,
// timeout or interruption closes that scope before the caller can finish.
export const layer = (dryRun = false, json = false) => Layer.effect(Command,
    Effect.gen(function* () {
        const spawner = yield* ChildProcessSpawner.ChildProcessSpawner;
        return { run: (call: CommandCall) => Effect.scoped(Effect.gen(function* () {
            if (dryRun && call.mutates !== false) {
                yield* Effect.sync(() => process.stderr.write(json
                    ? `${JSON.stringify({ dryRun: true, command: call.command, args: call.args, cwd: call.cwd, envKeys: Object.keys(call.env ?? {}) })}\n`
                    : `dry-run: ${describe(call)}\n`));
                return { exitCode: 0, stdout: "", stderr: "" };
            }
            const child = yield* spawner.spawn(ChildProcess.make(call.command, [...(call.args ?? [])], {
                ...(call.cwd === undefined ? {} : { cwd: call.cwd }),
                ...(call.env === undefined ? {} : { env: { ...call.env } }),
                extendEnv: true,
                stdin: call.inherit ? "inherit" : "ignore",
                ...(call.inherit && !json ? { stdout: "inherit" as const, stderr: "inherit" as const } : {}),
                forceKillAfter: "2 seconds",
            }));
            const collect = (stream: Stream.Stream<Uint8Array, unknown>, channel: "stdout" | "stderr") => {
                const onOutput = call.onOutput;
                const observe = (text: string) => Effect.gen(function* () {
                    if (json && call.inherit) yield* Effect.sync(() => { process.stderr.write(`${JSON.stringify({ command: call.command, channel, text })}\n`); });
                    if (onOutput !== undefined) yield* onOutput(text, channel);
                });
                return Stream.mkString(Stream.tap(Stream.decodeText(stream), observe));
            };
            const [stdout, stderr, code] = yield* Effect.all([
                call.inherit && !json ? Effect.succeed("") : collect(child.stdout, "stdout"),
                call.inherit && !json ? Effect.succeed("") : collect(child.stderr, "stderr"),
                child.exitCode,
            ], { concurrency: "unbounded" });
            const exitCode = Number(code);
            if (call.check !== false && exitCode !== 0) {
                return yield* Effect.fail(new CommandFailed({
                    exitCode,
                    message: `${describe(call)} exited with ${exitCode}${stderr ? `:\n${stderr.trimEnd()}` : ""}`,
                }));
            }
            return { exitCode, stdout, stderr };
        })).pipe(Effect.mapError((error) => error instanceof CommandFailed ? error : new CommandFailed({
            exitCode: 1, message: `${describe(call)} could not run: ${error instanceof Error ? error.message : String(error)}`,
        }))) };
    })
);

export const run = (call: CommandCall) => Command.use((service) => service.run(call));
