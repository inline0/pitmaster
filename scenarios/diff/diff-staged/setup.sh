#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-12T00:00:05+0000"
export GIT_COMMITTER_DATE="2024-01-12T00:00:05+0000"

cat > file.txt <<'EOF'
line one
line two
line three
EOF

git add file.txt
git commit -m "Initial commit with three lines"

cat > file.txt <<'EOF'
line one
line two modified
line three
EOF

git add file.txt
