#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-22T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-22T00:00:00+0000"

cat > tracked.txt <<'EOF'
tracked
EOF

git add tracked.txt
git commit -m initial >/dev/null
