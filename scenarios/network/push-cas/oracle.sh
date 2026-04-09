#!/usr/bin/env bash
set -euo pipefail

project_root="$PWD/projects"
source_dir="$PWD/source"
remote_dir="$project_root/remote.git"
clone_dir="$PWD/git-clone"
mkdir -p "$project_root"

git init -b main "$source_dir" >/dev/null
git init --bare -b main "$remote_dir" >/dev/null
git -C "$source_dir" config user.email test@example.com
git -C "$source_dir" config user.name Test
git -C "$remote_dir" config http.receivepack true
printf 'hello push parity\n' > "$source_dir/README.md"
git -C "$source_dir" add README.md
git -C "$source_dir" commit -m initial >/dev/null
git -C "$source_dir" remote add origin "$remote_dir"
git -C "$source_dir" push origin main >/dev/null

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0",$e,$m); $n=stream_socket_get_name($s,false); fclose($s); echo substr(strrchr($n, ":"), 1);')"
backend="$(git --exec-path)/git-http-backend"
PITMASTER_GIT_HTTP_PROJECT_ROOT="$project_root" \
PITMASTER_GIT_HTTP_BACKEND="$backend" \
php -S "127.0.0.1:$port" "$PITMASTER_ROOT/tests/Fixtures/git_http_backend_router.php" \
  > .server.log 2> .server.err &
server_pid=$!

cleanup() {
  kill "$server_pid" >/dev/null 2>&1 || true
  wait "$server_pid" 2>/dev/null || true
}
trap cleanup EXIT

for _ in $(seq 1 50); do
  if curl -fsS "http://127.0.0.1:$port/health" >/dev/null 2>&1; then
    break
  fi
  sleep 0.1
done

remote_url="http://127.0.0.1:$port/remote.git"
GIT_TERMINAL_PROMPT=0 git clone "$remote_url" "$clone_dir" >/dev/null
git -C "$clone_dir" config user.email test@example.com
git -C "$clone_dir" config user.name Test

printf 'remote advance\n' > "$source_dir/remote.txt"
git -C "$source_dir" add remote.txt
git -C "$source_dir" commit -m remote-advance >/dev/null
git -C "$source_dir" push origin main >/dev/null
advanced_head="$(git -C "$remote_dir" rev-parse refs/heads/main)"

printf 'pit lease push\n' > "$clone_dir/lease.txt"
git -C "$clone_dir" add lease.txt
git -C "$clone_dir" commit -m 'Pit lease' >/dev/null
git -C "$clone_dir" push --force-with-lease=refs/heads/main:"$advanced_head" origin main >/dev/null

git -C "$remote_dir" ls-tree -r --full-tree refs/heads/main > .remote-tree.txt
git -C "$remote_dir" show refs/heads/main:lease.txt > .remote-file.txt

trap - EXIT
cleanup
