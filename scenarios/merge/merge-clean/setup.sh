#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

cat > file.txt <<'EOF'
line 1
line 2
line 3
line 4
line 5
line 6
line 7
line 8
line 9
line 10
EOF

git add file.txt
git commit -m "Initial commit"

git checkout -b feature

cat > file.txt <<'EOF'
line 1
line 2
modified on feature
line 4
line 5
line 6
line 7
line 8
line 9
line 10
EOF

git add file.txt
git commit -m "Modify line 3 on feature branch"

git checkout main

cat > file.txt <<'EOF'
line 1
line 2
line 3
line 4
line 5
line 6
line 7
line 8
modified on main
line 10
EOF

git add file.txt
git commit -m "Modify line 9 on main branch"
