#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-22T00:00:04+0000"
export GIT_COMMITTER_DATE="2024-01-22T00:00:04+0000"

cat > a.txt <<'EOF'
v1
EOF

git add a.txt
git commit -m first >/dev/null

cat > a.txt <<'EOF'
v2
EOF

export GIT_AUTHOR_DATE="2024-01-22T00:00:05+0000"
export GIT_COMMITTER_DATE="2024-01-22T00:00:05+0000"
git add a.txt
git commit -m second >/dev/null

cat > a.txt <<'EOF'
worktree
EOF
