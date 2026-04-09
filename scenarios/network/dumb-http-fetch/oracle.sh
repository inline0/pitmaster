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
git -C source remote set-url origin "$(pwd)/docroot/remote.git"

cat > source/README.md <<'EOF'
dumb http fetch
remote advance
EOF
cat > source/notes.txt <<'EOF'
fetched over dumb http
EOF
git -C source add README.md notes.txt
git -C source commit -m advance >/dev/null
git -C source push origin main >/dev/null
git -C docroot/remote.git repack -ad >/dev/null
git -C docroot/remote.git update-server-info

git -C git-clone fetch origin >/dev/null
origin_main="$(git -C git-clone rev-parse refs/remotes/origin/main)"
remote_main="$(git -C docroot/remote.git rev-parse refs/heads/main)"

{
    if [ "$origin_main" = "$remote_main" ]; then
        printf 'origin.main_matches_remote=yes\n'
    else
        printf 'origin.main_matches_remote=no\n'
    fi
    if [ -f git-clone/notes.txt ]; then
        printf 'notes.exists=yes\n'
    else
        printf 'notes.exists=no\n'
    fi
} > .fetch-state

kill "$server_pid" >/dev/null 2>&1 || true
wait "$server_pid" 2>/dev/null || true
