#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-08T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-08T00:00:00+0000"

cat > app.txt <<'EOF'
hello
EOF

git add app.txt
git commit -m "Initial commit"
git tag v1.0
git tag -a v1.1 -m "Release 1.1"
