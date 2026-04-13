#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-16T00:00:10+0000"
export GIT_COMMITTER_DATE="2024-01-16T00:00:10+0000"

cat > app.txt <<'EOF'
base
EOF

git add app.txt
git commit -m base >/dev/null
