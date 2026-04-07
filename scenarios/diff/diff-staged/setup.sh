#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

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
