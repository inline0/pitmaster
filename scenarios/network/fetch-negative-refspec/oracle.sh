#!/usr/bin/env bash
set -euo pipefail

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); $n=stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($n, ":"), 1);')"
base_url="http://127.0.0.1:$port"
git_exec_path="$(git --exec-path)"
PITMASTER_GIT_HTTP_PROJECT_ROOT="$(pwd)/projects" \
PITMASTER_GIT_HTTP_BACKEND="$git_exec_path/git-http-backend" \
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
git -C git-clone config remote.origin.fetch '+refs/heads/*:refs/remotes/origin/*'
git -C git-clone config --add remote.origin.fetch '^refs/heads/feature'

cat > source/main.txt <<'EOF'
main branch
EOF
git -C source add main.txt
git -C source commit -m main-update >/dev/null
git -C source push origin main >/dev/null
git -C source checkout -b feature >/dev/null
cat > source/feature.txt <<'EOF'
feature branch
EOF
git -C source add feature.txt
git -C source commit -m feature-update >/dev/null
git -C source push origin feature >/dev/null
git -C source checkout main >/dev/null

git -C git-clone fetch origin >/dev/null

{
    if [ "$(git -C git-clone rev-parse refs/remotes/origin/main)" = "$(git -C projects/remote.git rev-parse refs/heads/main)" ]; then
        printf 'origin.main_matches_remote=yes\n'
    else
        printf 'origin.main_matches_remote=no\n'
    fi
    if [ -f git-clone/.git/refs/remotes/origin/feature ]; then
        printf 'origin.feature=present\n'
    else
        printf 'origin.feature=absent\n'
    fi
    printf 'fetch.refspecs=%s\n' "$(git -C git-clone config --get-all remote.origin.fetch | paste -sd, -)"
} > .negative-refspec-state

kill "$server_pid" >/dev/null 2>&1 || true
wait "$server_pid" 2>/dev/null || true
