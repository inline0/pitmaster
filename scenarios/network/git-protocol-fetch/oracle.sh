#!/usr/bin/env bash
set -euo pipefail

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); $n=stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($n, ":"), 1);')"
git daemon \
    --verbose --reuseaddr --export-all --base-path="$(pwd)/export" \
    --listen=127.0.0.1 --port="$port" "$(pwd)/export" \
    > .scenario-daemon.log 2> .scenario-daemon.err &
daemon_pid=$!

for _ in $(seq 1 50); do
    if php -r '$port=(int) $argv[1]; $s=@stream_socket_client("tcp://127.0.0.1:$port", $e, $m, 1); if ($s) { fclose($s); exit(0);} exit(1);' "$port"; then
        break
    fi
    sleep 0.1
done

git clone "git://127.0.0.1:$port/remote.git" git-clone >/dev/null 2>&1
printf 'pack_header=PACK\n' > .git-protocol-fetch-state

kill "$daemon_pid" >/dev/null 2>&1 || true
wait "$daemon_pid" 2>/dev/null || true
