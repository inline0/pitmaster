#!/usr/bin/env bash
set -euo pipefail

cleanup() {
    if [ -n "${server_pid:-}" ]; then
        kill "$server_pid" >/dev/null 2>&1 || true
        wait "$server_pid" 2>/dev/null || true
    fi
    if [ -n "${runtime_root:-}" ] && [ -d "$runtime_root" ]; then
        rm -rf "$runtime_root"
    fi
}

on_error() {
    if [ -f .scenario-sshd.log ]; then
        cat .scenario-sshd.log >&2 || true
    fi
    if [ -f .scenario-sshd.stderr.log ]; then
        cat .scenario-sshd.stderr.log >&2 || true
    fi
}

trap cleanup EXIT
trap on_error ERR

port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); $n=stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($n, ":"), 1);')"
user_name="$(id -un)"
url="ssh://$user_name@127.0.0.1:$port$(pwd)/remote.git"
runtime_root="$(mktemp -d)"

cp .scenario-host-key "$runtime_root/host_key"
cp .scenario-authorized-keys "$runtime_root/authorized_keys"
cp .scenario-client-key "$runtime_root/client_key"
chmod 600 "$runtime_root/host_key" "$runtime_root/authorized_keys" "$runtime_root/client_key"

cat > .scenario-sshd-config <<EOF
Port $port
ListenAddress 127.0.0.1
HostKey $runtime_root/host_key
PidFile $runtime_root/sshd.pid
AuthorizedKeysFile $runtime_root/authorized_keys
PasswordAuthentication no
KbdInteractiveAuthentication no
ChallengeResponseAuthentication no
PubkeyAuthentication yes
PermitRootLogin no
StrictModes no
UsePAM no
AllowUsers $user_name
LogLevel VERBOSE
EOF

/usr/sbin/sshd -D -f "$(pwd)/.scenario-sshd-config" -E "$(pwd)/.scenario-sshd.log" \
    > .scenario-sshd.stdout.log 2> .scenario-sshd.stderr.log &
server_pid=$!

host_key="$(awk '{print $1 " " $2}' .scenario-host-key.pub)"
printf '[127.0.0.1]:%s %s\n' "$port" "$host_key" > "$runtime_root/known_hosts"

for _ in $(seq 1 50); do
    if php -r '$port=(int) $argv[1]; $s=@stream_socket_client("tcp://127.0.0.1:$port", $e, $m, 1); if ($s) { fclose($s); exit(0);} exit(1);' "$port"; then
        break
    fi
    sleep 0.1
done

export GIT_SSH_COMMAND="ssh -i $runtime_root/client_key -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=$runtime_root/known_hosts -p $port"

git clone "$url" git-clone

git -C source config user.email test@pitmaster.dev
git -C source config user.name "Test User"
cat > source/README.md <<'EOF'
ssh fetch update
EOF
git -C source add README.md
GIT_AUTHOR_DATE='@1701000100 +0000' \
GIT_COMMITTER_DATE='@1701000100 +0000' \
git -C source commit -m fetch-update >/dev/null
git -C source push origin main >/dev/null

git -C git-clone fetch origin >/dev/null
git -C git-clone reset --hard origin/main >/dev/null
git -C git-clone config user.email test@pitmaster.dev
git -C git-clone config user.name "Test User"
cat > git-clone/pit.txt <<'EOF'
ssh push
EOF
git -C git-clone add pit.txt
GIT_AUTHOR_DATE='@1701000200 +0000' \
GIT_COMMITTER_DATE='@1701000200 +0000' \
git -C git-clone commit -m 'ssh push' >/dev/null
git -C git-clone push origin main >/dev/null

{
    printf 'head=%s\n' "$(git -C git-clone rev-parse HEAD)"
    printf 'remote_head=%s\n' "$(git -C remote.git rev-parse refs/heads/main)"
    printf 'origin_main=%s\n' "$(git -C git-clone rev-parse refs/remotes/origin/main)"
    printf 'tags=%s\n' "$(git -C git-clone tag --list --sort=refname)"
} > .ssh-state
