#!/usr/bin/env bash
set -euo pipefail
git init --initial-branch=main >/dev/null 2>&1
git config user.name Test
git config user.email test@example.com
GIT_AUTHOR_DATE='2024-03-02T00:00:00+0000' GIT_COMMITTER_DATE='2024-03-02T00:00:00+0000' git commit --allow-empty -m client >/dev/null
mkdir projects
git init --bare --initial-branch=main projects/remote.git >/dev/null 2>&1
git init --initial-branch=main source >/dev/null 2>&1
git -C source config user.name Test
git -C source config user.email test@example.com
printf 'main\n' > source/file.txt
git -C source add file.txt
GIT_AUTHOR_DATE='2024-03-02T00:00:01+0000' GIT_COMMITTER_DATE='2024-03-02T00:00:01+0000' git -C source commit -m main >/dev/null
git -C source checkout -b next >/dev/null 2>&1
printf 'next\n' >> source/file.txt
git -C source add file.txt
GIT_AUTHOR_DATE='2024-03-02T00:00:02+0000' GIT_COMMITTER_DATE='2024-03-02T00:00:02+0000' git -C source commit -m next >/dev/null
git -C source remote add origin "$(pwd)/projects/remote.git"
git -C source push origin main next >/dev/null 2>&1
port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0"); $n=stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($n, ":"), 1);')"
PITMASTER_GIT_HTTP_PROJECT_ROOT="$(pwd)/projects" \
PITMASTER_GIT_HTTP_BACKEND="$(git --exec-path)/git-http-backend" \
php -d "pitmaster.test.workspace=$(pwd)" -S "127.0.0.1:$port" "$PITMASTER_ROOT/tests/Fixtures/git_http_backend_router.php" > .scenario-server.log 2> .scenario-server.err &
echo $! > .scenario-server.pid
for _ in $(seq 1 50); do
    if curl -fsS "http://127.0.0.1:$port/health" >/dev/null 2>&1; then break; fi
    sleep 0.1
done
git remote add origin "http://127.0.0.1:$port/remote.git"
git config fetch.followRemoteHEAD always
git fetch origin >/dev/null 2>&1
git -C projects/remote.git symbolic-ref HEAD refs/heads/next
