#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
mkdir -p src docs
printf 'root\n' > README.md
printf 'src v1\n' > src/app.txt
printf 'docs v1\n' > docs/guide.txt
git add .
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit -m initial >/dev/null
printf 'src v2\n' > src/app.txt
printf 'docs v2\n' > docs/guide.txt
git add .
GIT_AUTHOR_DATE='@1700000060 +0000' \
GIT_COMMITTER_DATE='@1700000060 +0000' \
git commit -m update-main >/dev/null
