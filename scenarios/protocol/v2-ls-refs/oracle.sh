#!/usr/bin/env bash
set -euo pipefail

url="$(cat .remote-url)"
trace=".git-trace-v2-ls-refs"
GIT_TRACE_PACKET=1 git -c protocol.version=2 clone --no-checkout "$url" git-clone >/dev/null 2>"$trace"

php -r '
$trace = file($argv[1], FILE_IGNORE_NEW_LINES) ?: [];
$lines = [];
$capturing = false;

foreach ($trace as $line) {
    $needle = "clone>";
    $pos = strpos($line, $needle);
    if ($pos === false) {
        continue;
    }

    $payload = trim(substr($line, $pos + strlen($needle)));
    $payload = preg_replace("/agent=[^ ]+/", "agent=<normalized>", $payload) ?? $payload;

    if (!$capturing) {
        if ($payload !== "command=ls-refs") {
            continue;
        }
        $capturing = true;
    }

    $lines[] = $payload;

    if ($payload === "0000") {
        break;
    }
}

if ($lines === []) {
    fwrite(STDERR, "Unable to locate ls-refs request in trace\n");
    exit(1);
}

file_put_contents($argv[2], implode("\n", $lines) . "\n");
' "$trace" .v2-ls-refs-lines
