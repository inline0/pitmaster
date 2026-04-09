#!/usr/bin/env bash
set -euo pipefail

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); $n=stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($n, ":"), 1);')"
base_url="http://127.0.0.1:$port"
php -S "127.0.0.1:$port" "$PITMASTER_ROOT/tests/Fixtures/protocol_router.php" \
    > .scenario-server.log 2> .scenario-server.err &
server_pid=$!

for _ in $(seq 1 50); do
    if curl -fsS "$base_url/health" >/dev/null 2>&1; then
        break
    fi
    sleep 0.1
done

GIT_TERMINAL_PROMPT=0 git clone "$base_url/discover-404" git-clone >/dev/null 2>&1 || true
kill "$server_pid" >/dev/null 2>&1 || true
wait "$server_pid" 2>/dev/null || true

if [ -e git-clone ]; then
    printf 'target_exists=yes\n' > .cleanup-state
else
    printf 'target_exists=no\n' > .cleanup-state
fi
