#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

cat > file.txt <<'EOF'
initial content
EOF

git add file.txt
git commit -m "Initial commit"

git tag v0.1

git tag -a v1.0 -m "Release version 1.0"

cat > file.txt <<'EOF'
updated content
EOF

git add file.txt
git commit -m "Second commit"

git tag v2.0
