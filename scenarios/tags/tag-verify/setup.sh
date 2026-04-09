#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
cat > tracked.txt <<'EOF'
verify parity
EOF
git add tracked.txt
GIT_AUTHOR_DATE='@1712563200 +0200' GIT_COMMITTER_DATE='@1712566800 +0200' git commit --quiet -m initial
