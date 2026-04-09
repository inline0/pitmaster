#!/usr/bin/env bash
set -euo pipefail

git checkout "$(cat .detach-id)" >/dev/null 2>&1

cat > detached.txt <<'EOF'
detached
EOF

git add detached.txt
export GIT_AUTHOR_DATE="2024-01-15T00:00:10+0000"
export GIT_COMMITTER_DATE="2024-01-15T00:00:10+0000"
git commit -m detached >/dev/null
