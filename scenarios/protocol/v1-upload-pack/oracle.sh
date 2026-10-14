#!/usr/bin/env bash
set -euo pipefail

url="$(cat .remote-url)"
trace=".git-trace-upload-pack"

GIT_TRACE_PACKET=1 git -c protocol.version=1 clone "$url" git-clone >/dev/null 2>"$trace"

# Compare the HTTP request body rather than the trace's later connection flush.
php "$(dirname "${BASH_SOURCE[0]}")/print_actual.php" "$(cat .scenario-capture-dir)" | php -r '
$request = stream_get_contents(STDIN);
echo str_replace(" thin-pack", "", $request);
' > .upload-pack-lines
