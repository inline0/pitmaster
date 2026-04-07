#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

cat > file.txt <<'EOF'
hello world
EOF

git add file.txt
git commit -m "Initial commit"
