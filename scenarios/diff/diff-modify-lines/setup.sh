#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

cat > file.txt <<'EOF'
first line
second line
third line
fourth line
EOF

git add file.txt
git commit -m "Initial commit with four lines"

cat > file.txt <<'EOF'
first line
modified second line
third line
modified fourth line
EOF
