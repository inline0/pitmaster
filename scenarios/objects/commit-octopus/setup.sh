#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

cat > base.txt <<'EOF'
base content
EOF

git add base.txt
git commit -m "Base commit"

git checkout -b branch-a

cat > a.txt <<'EOF'
content from branch a
EOF

git add a.txt
git commit -m "Add file on branch-a"

git checkout main
git checkout -b branch-b

cat > b.txt <<'EOF'
content from branch b
EOF

git add b.txt
git commit -m "Add file on branch-b"

git checkout main
git merge branch-a branch-b -m "Octopus merge of branch-a and branch-b"
