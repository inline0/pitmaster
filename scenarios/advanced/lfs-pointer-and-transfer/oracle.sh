#!/bin/bash
set -e

storage="$(pwd)/.lfs-storage"
mkdir -p "$storage"
router="$PITMASTER_ROOT/tests/Fixtures/lfs_router.php"
port=$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $errno, $errstr); if ($s === false) { fwrite(STDERR, $errstr); exit(1); } $name=stream_socket_get_name($s, false); fclose($s); if ($name === false) { exit(1); } echo substr(strrchr($name, ":"), 1);')

LFS_STORAGE_DIR="$storage" php -S "127.0.0.1:$port" "$router" > .lfs-server.out.log 2> .lfs-server.err.log &
server_pid=$!
trap 'kill "$server_pid" >/dev/null 2>&1 || true' EXIT

for _ in $(seq 1 40); do
    if curl -fsS "http://127.0.0.1:$port/__health" >/dev/null; then
        break
    fi
    sleep 0.1
done

remote="$(pwd)/remote.git"
work="$(pwd)/work"
clone="$(pwd)/clone"

git init --bare --initial-branch=main "$remote" >/dev/null
git init --initial-branch=main "$work" >/dev/null
git -C "$work" config user.email test@pitmaster.dev
git -C "$work" config user.name "Test User"
git -C "$work" lfs install --local >/dev/null
git -C "$work" lfs track '*.bin' >/dev/null
git -C "$work" config lfs.url "http://127.0.0.1:$port/repo.git/info/lfs"
printf 'payload from lfs\n' > "$work/payload.bin"
git -C "$work" add .gitattributes payload.bin
git -C "$work" commit -m "Add payload" >/dev/null
git -C "$work" remote add origin "$remote"
git -C "$work" push origin main >/dev/null
git -C "$work" show HEAD:payload.bin > pointer.txt

GIT_LFS_SKIP_SMUDGE=1 git clone "$remote" "$clone" >/dev/null
git -C "$clone" lfs install --local >/dev/null
git -C "$clone" config lfs.url "http://127.0.0.1:$port/repo.git/info/lfs"
git -C "$clone" lfs pull >/dev/null
cat "$clone/payload.bin" > downloaded.txt
