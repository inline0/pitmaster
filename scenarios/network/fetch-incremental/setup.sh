#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit --allow-empty -m init >/dev/null

mkdir -p projects
git init --bare --initial-branch=main projects/remote.git >/dev/null
git init --initial-branch=main source >/dev/null
git -C source config user.email test@pitmaster.dev
git -C source config user.name "Test User"
cat > source/README.md <<'EOF'
incremental fetch
EOF
git -C source add README.md
git -C source commit -m initial >/dev/null
git -C source remote add origin "$(pwd)/projects/remote.git"
git -C source push origin main >/dev/null
