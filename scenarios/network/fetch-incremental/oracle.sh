#!/usr/bin/env bash
set -euo pipefail

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); $n=stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($n, ":"), 1);')"
base_url="http://127.0.0.1:$port"
PITMASTER_GIT_HTTP_PROJECT_ROOT="$(pwd)/projects" \
PITMASTER_GIT_HTTP_BACKEND="/Applications/Xcode.app/Contents/Developer/usr/libexec/git-core/git-http-backend" \
php -S "127.0.0.1:$port" "$PITMASTER_ROOT/tests/Fixtures/git_http_backend_router.php" \
    > .scenario-server.log 2> .scenario-server.err &
server_pid=$!

for _ in $(seq 1 50); do
    if curl -fsS "$base_url/health" >/dev/null 2>&1; then
        break
    fi
    sleep 0.1
done

git -C source remote set-url origin "$(pwd)/projects/remote.git"
git clone "$base_url/remote.git" git-clone >/dev/null 2>&1
before="$(find git-clone/.git/objects/pack -name '*.pack' | wc -l | tr -d ' ')"

cat > source/remote.txt <<'EOF'
first advance
EOF
git -C source add remote.txt
git -C source commit -m remote-update >/dev/null
git -C source push origin main >/dev/null

git -C git-clone fetch origin >/dev/null
after_incremental="$(find git-clone/.git/objects/pack -name '*.pack' | wc -l | tr -d ' ')"
git -C git-clone fetch origin >/dev/null
after_noop="$(find git-clone/.git/objects/pack -name '*.pack' | wc -l | tr -d ' ')"

{
    if [ "$(git -C git-clone rev-parse refs/remotes/origin/main)" = "$(git -C projects/remote.git rev-parse refs/heads/main)" ]; then
        printf 'origin.main_matches_remote=yes\n'
    else
        printf 'origin.main_matches_remote=no\n'
    fi
    if [ "$after_incremental" -gt "$before" ]; then
        printf 'incremental_added=yes\n'
    else
        printf 'incremental_added=no\n'
    fi
    if [ "$after_noop" = "$after_incremental" ]; then
        printf 'noop_stable=yes\n'
    else
        printf 'noop_stable=no\n'
    fi
} > .fetch-state

kill "$server_pid" >/dev/null 2>&1 || true
wait "$server_pid" 2>/dev/null || true
