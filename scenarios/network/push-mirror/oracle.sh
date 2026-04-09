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
git -C "$source_dir" checkout -b topic >/dev/null
printf 'topic base\n' > "$source_dir/topic.txt"
git -C "$source_dir" add topic.txt
git -C "$source_dir" commit -m topic-base >/dev/null
git -C "$source_dir" checkout main >/dev/null
git -C "$source_dir" tag oldtag
git -C "$source_dir" remote add origin "$remote_dir"
git -C "$source_dir" push origin --all >/dev/null
git -C "$source_dir" push origin --tags >/dev/null

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
git -C "$clone_dir" checkout -b topic origin/topic >/dev/null
git -C "$clone_dir" checkout main >/dev/null
git -C "$clone_dir" branch -D topic >/dev/null
git -C "$clone_dir" checkout -b feature >/dev/null
git -C "$clone_dir" checkout main >/dev/null
git -C "$clone_dir" tag newtag
git -C "$clone_dir" tag -d oldtag >/dev/null
git -C "$clone_dir" push --mirror origin >/dev/null

{
  git -C "$remote_dir" for-each-ref --format='%(refname)' refs/heads refs/tags | sort
  printf '[main]\n'
  git -C "$remote_dir" ls-tree -r --full-tree refs/heads/main
  printf '[feature]\n'
  git -C "$remote_dir" ls-tree -r --full-tree refs/heads/feature
  printf '[newtag]\n'
  git -C "$remote_dir" ls-tree -r --full-tree refs/tags/newtag
} > .remote-refs.txt

trap - EXIT
cleanup
