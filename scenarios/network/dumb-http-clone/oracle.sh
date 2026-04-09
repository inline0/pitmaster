#!/usr/bin/env bash
set -euo pipefail

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); $n=stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($n, ":"), 1);')"
base_url="http://127.0.0.1:$port"
php -S "127.0.0.1:$port" -t "$(pwd)/docroot" \
    > .scenario-server.log 2> .scenario-server.err &
server_pid=$!

for _ in $(seq 1 50); do
    if curl -fsS "$base_url/health.txt" >/dev/null 2>&1; then
        break
    fi
    sleep 0.1
done

git clone "$base_url/remote.git" git-clone >/dev/null 2>&1

{
    printf 'head=%s\n' "$(git -C git-clone rev-parse HEAD)"
    printf 'origin.main=%s\n' "$(git -C git-clone rev-parse refs/remotes/origin/main)"
    printf 'tag.v1.0=%s\n' "$(git -C git-clone rev-parse refs/tags/v1.0)"
    printf 'readme=%s\n' "$(tr -d '\n' < git-clone/README.md)"
} > .clone-state

kill "$server_pid" >/dev/null 2>&1 || true
wait "$server_pid" 2>/dev/null || true
