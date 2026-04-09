#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

mkdir -p projects
git init --bare --initial-branch=main projects/remote.git >/dev/null
git init --initial-branch=main source >/dev/null

git -C source config user.email test@pitmaster.dev
git -C source config user.name "Test User"

cat > source/README.md <<'EOF'
hello clone refspec
EOF
git -C source add README.md
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git -C source commit -m initial >/dev/null
git -C source remote add origin "$(pwd)/projects/remote.git"
git -C source push origin main >/dev/null

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); $n=stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($n, ":"), 1);')"
echo "$port" > .scenario-port
echo "http://127.0.0.1:$port/remote.git" > .remote-url

git_exec_path="$(git --exec-path)"
PITMASTER_GIT_HTTP_PROJECT_ROOT="$(pwd)/projects" \
PITMASTER_GIT_HTTP_BACKEND="$git_exec_path/git-http-backend" \
php -S "127.0.0.1:$port" "$PITMASTER_ROOT/tests/Fixtures/git_http_backend_router.php" \
    > .scenario-server.log 2> .scenario-server.err &

echo $! > .scenario-server.pid

for _ in $(seq 1 50); do
    if curl -fsS "http://127.0.0.1:$port/health" >/dev/null 2>&1; then
        exit 0
    fi
    sleep 0.1
done

echo "scenario server did not become ready" >&2
exit 1
