#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-09T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-09T00:00:00+0000"

cat > a.txt <<'EOF'
line 1
line 2
line 3
EOF

git add a.txt
git commit -m base >/dev/null

cat > a.txt <<'EOF'
line 1
change
line 3
EOF

git add a.txt
export GIT_AUTHOR_DATE="2024-01-09T00:00:01+0000"
export GIT_COMMITTER_DATE="2024-01-09T00:00:01+0000"
git commit -m change >/dev/null

revert_id=$(git rev-parse HEAD)

git checkout -b other HEAD~1 >/dev/null

cat > a.txt <<'EOF'
line 1
other
line 3
EOF

git add a.txt
export GIT_AUTHOR_DATE="2024-01-09T00:00:02+0000"
export GIT_COMMITTER_DATE="2024-01-09T00:00:02+0000"
git commit -m other >/dev/null

printf '%s\n' "$revert_id" > .revert-id
