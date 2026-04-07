#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

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
