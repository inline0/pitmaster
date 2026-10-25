import * as NodeRuntime from "@effect/platform-node/NodeRuntime";
import * as NodeServices from "@effect/platform-node/NodeServices";
import * as Cause from "effect/Cause";
import * as CliError from "effect/cli/CliError";
import * as Cli from "effect/cli/Command";
import * as Flag from "effect/cli/Flag";
import * as Data from "effect/Data";
import * as Effect from "effect/Effect";
import type * as FileSystem from "effect/FileSystem";
import * as Layer from "effect/Layer";
import * as Option from "effect/Option";
import * as Runtime from "effect/Runtime";
import * as Command from "./command.ts";

export interface ScriptInput { readonly json: boolean; readonly dryRun: boolean; }
export class ScriptExit extends Data.TaggedError("ScriptExit")<{
    readonly code: number; readonly message?: string;
}> {
    override get [Runtime.errorExitCode]() { return this.code; }
}
export const fail = (message: string, code = 1) => Effect.fail(new ScriptExit({ code, message }));
export const runtimeLayer = (options: ScriptInput) => Command.layer(options.dryRun, options.json).pipe(
    Layer.provideMerge(NodeServices.layer)
);
export const script = <const Name extends string, const Config extends Cli.Command.Config, E>(
    name: Name,
    options: {
        readonly description: string;
        readonly flags: Config;
        readonly run: (input: Cli.Command.Config.Infer<Config> & ScriptInput) =>
            Effect.Effect<void, E, Command.Command | FileSystem.FileSystem>;
    },
) => Cli.make(name, {
    ...options.flags,
    json: Flag.Boolean("json").pipe(Flag.withDefault(false), Flag.withDescription("Print machine-readable results")),
    dryRun: Flag.Boolean("dry-run").pipe(Flag.withDefault(false), Flag.withDescription("Preview subprocesses without executing them")),
}, (input) => {
    const parsed = input as Cli.Command.Config.Infer<Config> & ScriptInput;
    return options.run(parsed).pipe(Effect.provide(runtimeLayer(parsed)));
}).pipe(Cli.withDescription(options.description));

export const exitCodeOf = (cause: Cause.Cause<unknown>) => Effect.gen(function* () {
    if (Cause.hasInterruptsOnly(cause)) return 130;
    const error = Option.getOrUndefined(Cause.findErrorOption(cause));
    if (CliError.isCliError(error)) return 2;
    const message = error instanceof Error ? error.message : Cause.pretty(cause);
    if (message) yield* Effect.sync(() => process.stderr.write(`${message}\n`));
    return error instanceof ScriptExit ? error.code : error === undefined ? 1 : Runtime.getErrorExitCode(error);
});
export const runArguments = <Name extends string, Input, Context, E, R>(
    command: Cli.Command<Name, Input, Context, E, R>, argv: ReadonlyArray<string>,
) => Cli.runWith(command, { version: "1" })(argv).pipe(
    Effect.as(0 as number), Effect.catchCause(exitCodeOf), Effect.provide(NodeServices.layer),
) as Effect.Effect<number>;
export const main = <Name extends string, Input, Context, E, R>(command: Cli.Command<Name, Input, Context, E, R>) =>
    NodeRuntime.runMain(runArguments(command, process.argv.slice(2)).pipe(
        Effect.flatMap((code) => code === 0 ? Effect.void : Effect.fail(new ScriptExit({ code }))),
    ), { disableErrorReporting: true });
