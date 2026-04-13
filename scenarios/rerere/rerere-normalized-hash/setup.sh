#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
git config rerere.enabled true

cat > a.txt <<'EOF'
line1
line2
EOF

git add a.txt
GIT_AUTHOR_DATE='2024-01-22T00:00:10+0000' \
GIT_COMMITTER_DATE='2024-01-22T00:00:10+0000' \
git commit -m base >/dev/null

git checkout -b feature >/dev/null

cat > a.txt <<'EOF'
line1
feature
EOF

git add a.txt
GIT_AUTHOR_DATE='2024-01-22T00:00:11+0000' \
GIT_COMMITTER_DATE='2024-01-22T00:00:11+0000' \
git commit -m feature >/dev/null

git checkout main >/dev/null

cat > a.txt <<'EOF'
line1
main
EOF

git add a.txt
GIT_AUTHOR_DATE='2024-01-22T00:00:12+0000' \
GIT_COMMITTER_DATE='2024-01-22T00:00:12+0000' \
git commit -m main >/dev/null

git merge feature >/dev/null 2>&1 || true
