#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-12T00:00:01+0000"
export GIT_COMMITTER_DATE="2024-01-12T00:00:01+0000"

cat > file.txt <<'EOF'
line one
line two
line three
line four
line five
EOF

git add file.txt
git commit -m "Initial commit with five lines"

cat > file.txt <<'EOF'
line one
line three
line five
EOF
