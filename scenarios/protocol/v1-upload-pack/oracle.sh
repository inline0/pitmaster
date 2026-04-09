#!/usr/bin/env bash
set -euo pipefail

url="$(cat .remote-url)"
trace=".git-trace-upload-pack"

GIT_TRACE_PACKET=1 git -c protocol.version=1 clone "$url" git-clone >/dev/null 2>"$trace"

php -r '
$trace = file($argv[1], FILE_IGNORE_NEW_LINES) ?: [];
$lines = [];
foreach ($trace as $line) {
    $needle = "fetch-pack>";
    $pos = strpos($line, $needle);
    if ($pos === false) {
        continue;
    }
    $payload = trim(substr($line, $pos + strlen($needle)));
    $lines[] = preg_replace("/agent=[^ ]+/", "agent=<normalized>", $payload) ?? $payload;
    if (count($lines) === 4) {
        break;
    }
}
file_put_contents($argv[2], implode("\n", $lines) . "\n");
' "$trace" .upload-pack-lines
