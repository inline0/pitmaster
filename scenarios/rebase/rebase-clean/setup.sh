#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
git config core.logAllRefUpdates true

export GIT_AUTHOR_DATE="2024-01-12T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-12T00:00:00+0000"
cat > a.txt <<'EOF'
base
EOF
git add a.txt
git commit -m base >/dev/null

git checkout -b feature >/dev/null

export GIT_AUTHOR_DATE="2024-01-12T00:00:01+0000"
export GIT_COMMITTER_DATE="2024-01-12T00:00:01+0000"
cat > b.txt <<'EOF'
feature one
EOF
git add b.txt
git commit -m feature-one >/dev/null

export GIT_AUTHOR_DATE="2024-01-12T00:00:02+0000"
export GIT_COMMITTER_DATE="2024-01-12T00:00:02+0000"
cat > c.txt <<'EOF'
feature two
EOF
git add c.txt
git commit -m feature-two >/dev/null

git checkout main >/dev/null

export GIT_AUTHOR_DATE="2024-01-12T00:00:03+0000"
export GIT_COMMITTER_DATE="2024-01-12T00:00:03+0000"
cat > a.txt <<'EOF'
main
EOF
git add a.txt
git commit -m main >/dev/null

git checkout feature >/dev/null
