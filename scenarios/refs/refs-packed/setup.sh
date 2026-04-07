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

git branch feature-one
git branch feature-two

git tag v1.0
git tag -a v1.1 -m "Annotated tag v1.1"

cat > file.txt <<'EOF'
updated content
EOF

git add file.txt
git commit -m "Second commit"

git tag v2.0

git pack-refs --all
