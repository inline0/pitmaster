#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

mkdir -p export
git init --bare --initial-branch=main export/remote.git >/dev/null

cat > README.md <<'EOF'
hello git discovery
EOF
git add README.md
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit -m initial >/dev/null
git tag v1.0
git remote add origin "$(pwd)/export/remote.git"
git push origin main --tags >/dev/null

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); $n=stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($n, ":"), 1);')"
echo "$port" > .scenario-port
echo "git://127.0.0.1:$port/remote.git" > .remote-url

git daemon \
    --verbose --reuseaddr --export-all --base-path="$(pwd)/export" \
    --listen=127.0.0.1 --port="$port" "$(pwd)/export" \
    > .scenario-daemon.log 2> .scenario-daemon.err &

echo $! > .scenario-daemon.pid

for _ in $(seq 1 50); do
    if php -r '$port=(int) trim(file_get_contents(".scenario-port")); $s=@stream_socket_client("tcp://127.0.0.1:$port", $e, $m, 1); if ($s) { fclose($s); exit(0);} exit(1);'; then
        exit 0
    fi
    sleep 0.1
done

echo "scenario daemon did not become ready" >&2
exit 1
