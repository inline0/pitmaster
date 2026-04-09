#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-22T00:00:03+0000"
export GIT_COMMITTER_DATE="2024-01-22T00:00:03+0000"

cat > a.txt <<'EOF'
original
EOF

git add a.txt
git commit -m initial >/dev/null

cat > a.txt <<'EOF'
staged
EOF

git add a.txt
