#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

cat > modify-me.txt <<'EOF'
original content
EOF

cat > delete-me.txt <<'EOF'
this file will be deleted
EOF

git add modify-me.txt delete-me.txt
git commit -m "Initial commit with two files"

cat > modify-me.txt <<'EOF'
modified content
EOF
git add modify-me.txt

rm delete-me.txt

cat > untracked.txt <<'EOF'
this file is not tracked
EOF
