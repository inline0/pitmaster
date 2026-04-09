#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

git init --initial-branch=main source >/dev/null
git init --bare --initial-branch=main remote.git >/dev/null

git -C source config user.email test@pitmaster.dev
git -C source config user.name "Test User"

cat > source/README.md <<'EOF'
ssh transport
EOF
git -C source add README.md
GIT_AUTHOR_DATE='@1701000000 +0000' \
GIT_COMMITTER_DATE='@1701000000 +0000' \
git -C source commit -m initial >/dev/null
git -C source tag v1.0
git -C source remote add origin ../remote.git
git -C source push origin main --tags >/dev/null

ssh-keygen -q -t ed25519 -N "" -f .scenario-client-key >/dev/null
ssh-keygen -q -t ed25519 -N "" -f .scenario-host-key >/dev/null
cp .scenario-client-key.pub .scenario-authorized-keys
