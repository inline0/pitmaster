#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

mkdir -p dir/sub
cat > dir/sub/file.txt <<'EOF'
content
EOF

git add .
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit -m "tree fixture" >/dev/null
git write-tree >/dev/null

grep -aq 'TREE' .git/index
