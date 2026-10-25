import * as Console from "effect/Console";
import * as Effect from "effect/Effect";
import * as Flag from "effect/cli/Flag";
import { main, script } from "../lib/cli.ts";
import * as Command from "../lib/command.ts";
const command = script("command-fixture", {
    description: "Command gateway integration fixture.", flags: { code: Flag.Int("code").pipe(Flag.withDefault(0)) },
    run: (input) => Effect.gen(function* () {
        yield* Command.run({ command: process.execPath, args: ["-e", `console.log("child output");console.error("child error");process.exit(${input.code})`], inherit: true, env: { AUTH_SECRET: "never-log-this" } });
        yield* Console.log(JSON.stringify({ success: true }));
    }),
});
if (import.meta.main) main(command);
