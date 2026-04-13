#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-09T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-09T00:00:00+0000"

cat > file.txt <<'EOF'
hello world
EOF

git add file.txt
git commit -m "Initial commit"
