#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-06T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-06T00:00:00+0000"

cat > A <<'EOF'
foo
EOF

git add A
git commit -m base >/dev/null

git checkout -b rename >/dev/null
git mv A B
export GIT_AUTHOR_DATE="2024-01-06T00:00:01+0000"
export GIT_COMMITTER_DATE="2024-01-06T00:00:01+0000"
git commit -m rename >/dev/null

git checkout main >/dev/null
git rm A >/dev/null
export GIT_AUTHOR_DATE="2024-01-06T00:00:02+0000"
export GIT_COMMITTER_DATE="2024-01-06T00:00:02+0000"
git commit -m delete >/dev/null
