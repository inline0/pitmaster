#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

export GIT_AUTHOR_DATE="2024-01-16T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-16T00:00:00+0000"
cat > a.txt <<'EOF'
line 1
line 2
line 3
EOF
git add a.txt
git commit -m base >/dev/null

git checkout -b feature >/dev/null

export GIT_AUTHOR_DATE="2024-01-16T00:00:01+0000"
export GIT_COMMITTER_DATE="2024-01-16T00:00:01+0000"
cat > a.txt <<'EOF'
line 1
feature change
line 3
EOF
git add a.txt
git commit -m feature >/dev/null

git checkout main >/dev/null

export GIT_AUTHOR_DATE="2024-01-16T00:00:02+0000"
export GIT_COMMITTER_DATE="2024-01-16T00:00:02+0000"
cat > a.txt <<'EOF'
line 1
main change
line 3
EOF
git add a.txt
git commit -m main >/dev/null

git checkout feature >/dev/null
