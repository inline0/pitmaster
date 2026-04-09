#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

cat > a.txt <<'EOF'
base
EOF
git add a.txt
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit -m base >/dev/null

git checkout -b feature >/dev/null
cat > a.txt <<'EOF'
feature
EOF
git add a.txt
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit -m feature >/dev/null

git checkout main >/dev/null
cat > a.txt <<'EOF'
main
EOF
git add a.txt
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit -m main >/dev/null

git merge feature >/dev/null 2>&1 || true

cat > a.txt <<'EOF'
resolved
EOF
git add a.txt

grep -aq 'REUC' .git/index
