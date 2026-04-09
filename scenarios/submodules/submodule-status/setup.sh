#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

mkdir dep
git -C dep init -b main >/dev/null
git -C dep config user.email test@pitmaster.dev
git -C dep config user.name "Test User"
cat > dep/dep.txt <<'EOF'
dependency
EOF
git -C dep add dep.txt
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git -C dep commit -m dep >/dev/null

git -c protocol.file.allow=always submodule add ./dep vendor/lib >/dev/null
GIT_AUTHOR_DATE='@1700000060 +0000' \
GIT_COMMITTER_DATE='@1700000060 +0000' \
git commit -am "Add submodule" >/dev/null
