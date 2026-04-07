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
EOF

git add file.txt
git commit -m "Initial commit"

git checkout -b feature

cat > file.txt <<'EOF'
line 1
changed by feature
line 3
line 4
line 5
EOF

git add file.txt
git commit -m "Modify line 2 on feature branch"

git checkout main

cat > file.txt <<'EOF'
line 1
changed by main
line 3
line 4
line 5
EOF

git add file.txt
git commit -m "Modify line 2 on main branch"
