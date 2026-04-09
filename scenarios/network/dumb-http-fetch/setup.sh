#!/usr/bin/env bash
set -euo pipefail

mkdir -p docroot
printf 'ok\n' > docroot/health.txt

git init -b main source >/dev/null
git init --bare -b main docroot/remote.git >/dev/null
git -C source config user.email test@pitmaster.dev
git -C source config user.name "Test User"

cat > source/README.md <<'EOF'
dumb http fetch
EOF

git -C source add README.md
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git -C source commit -m initial >/dev/null
git -C source remote add origin "$(pwd)/docroot/remote.git"
git -C source push origin main >/dev/null
git -C docroot/remote.git repack -ad >/dev/null
git -C docroot/remote.git update-server-info
