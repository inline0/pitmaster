#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-17T00:10:00+0000"
export GIT_COMMITTER_DATE="2024-01-17T00:10:00+0000"

cat > a.txt <<'EOF'
base
EOF

git add a.txt
git commit -m base >/dev/null

git checkout -b feature >/dev/null

cat > new.txt <<'EOF'
feature file
EOF

git add new.txt
export GIT_AUTHOR_DATE="2024-01-17T00:10:01+0000"
export GIT_COMMITTER_DATE="2024-01-17T00:10:01+0000"
git commit -m feature >/dev/null

git checkout main >/dev/null

cat > new.txt <<'EOF'
untracked
EOF
