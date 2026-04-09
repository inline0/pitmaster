#!/usr/bin/env bash
set -euo pipefail

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); $n=stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($n, ":"), 1);')"

if GIT_TERMINAL_PROMPT=0 git ls-remote "git://127.0.0.1:$port/missing.git" >/dev/null 2>&1; then
    printf 'failed=no\n' > .git-protocol-error-state
else
    printf 'failed=yes\n' > .git-protocol-error-state
fi
