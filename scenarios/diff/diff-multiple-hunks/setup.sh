#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-12T00:00:03+0000"
export GIT_COMMITTER_DATE="2024-01-12T00:00:03+0000"

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
line 11
line 12
line 13
line 14
line 15
line 16
line 17
line 18
line 19
line 20
EOF

git add file.txt
git commit -m "Initial commit with 20 lines"

cat > file.txt <<'EOF'
line 1
line 2
changed line 3
line 4
line 5
line 6
line 7
line 8
line 9
line 10
line 11
line 12
line 13
line 14
line 15
line 16
line 17
changed line 18
line 19
line 20
EOF
