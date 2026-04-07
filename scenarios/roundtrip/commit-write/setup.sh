#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

cat > file.txt <<'EOF'
first version
EOF

git add file.txt
git commit -m "First"

cat > file.txt <<'EOF'
second version
EOF

git add file.txt
git commit -m "Second"
