#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
cat > tracked.txt <<'EOF'
missing object
EOF
git add tracked.txt
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit --quiet -m initial
