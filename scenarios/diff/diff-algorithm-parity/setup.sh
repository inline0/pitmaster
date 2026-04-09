#!/usr/bin/env bash
set -euo pipefail

git init --initial-branch=main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-11T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-11T00:00:00+0000"

cat > article.txt <<'EOF'
a
unique1
repeat
repeat
unique2
z
EOF

git add article.txt
git commit -m base >/dev/null

cat > article.txt <<'EOF'
a
unique1
unique2
repeat
repeat
z
EOF
