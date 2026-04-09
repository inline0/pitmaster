#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-08T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-08T00:00:00+0000"

cat > f.txt <<'EOF'
one
two
three
four
EOF

git add f.txt
git commit -m A >/dev/null

git checkout -b left >/dev/null
cat > f.txt <<'EOF'
B1
two
three
four
EOF
git add f.txt
export GIT_AUTHOR_DATE="2024-01-08T00:00:01+0000"
export GIT_COMMITTER_DATE="2024-01-08T00:00:01+0000"
git commit -m B >/dev/null
git branch left-base >/dev/null

git checkout main >/dev/null
git checkout -b right >/dev/null
cat > f.txt <<'EOF'
one
two
three
C2
EOF
git add f.txt
export GIT_AUTHOR_DATE="2024-01-08T00:00:02+0000"
export GIT_COMMITTER_DATE="2024-01-08T00:00:02+0000"
git commit -m C >/dev/null
git branch right-base >/dev/null

git checkout left >/dev/null
export GIT_AUTHOR_DATE="2024-01-08T00:00:03+0000"
export GIT_COMMITTER_DATE="2024-01-08T00:00:03+0000"
git merge right-base --no-edit >/dev/null
cat > f.txt <<'EOF'
B1
two
three
D2
EOF
git add f.txt
export GIT_AUTHOR_DATE="2024-01-08T00:00:04+0000"
export GIT_COMMITTER_DATE="2024-01-08T00:00:04+0000"
git commit -m D >/dev/null

git checkout right >/dev/null
export GIT_AUTHOR_DATE="2024-01-08T00:00:05+0000"
export GIT_COMMITTER_DATE="2024-01-08T00:00:05+0000"
git merge left-base --no-edit >/dev/null
cat > f.txt <<'EOF'
E1
two
three
C2
EOF
git add f.txt
export GIT_AUTHOR_DATE="2024-01-08T00:00:06+0000"
export GIT_COMMITTER_DATE="2024-01-08T00:00:06+0000"
git commit -m E >/dev/null
