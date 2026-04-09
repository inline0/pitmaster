#!/usr/bin/env bash
set -euo pipefail

git revert "$(cat .revert-id)" >/dev/null 2>&1 || true

cat > a.txt <<'EOF'
line 1
resolved
line 3
EOF

git add a.txt
export GIT_AUTHOR_DATE="2024-01-15T00:00:10+0000"
export GIT_COMMITTER_DATE="2024-01-15T00:00:10+0000"
git -c core.editor=true revert --continue >/dev/null
