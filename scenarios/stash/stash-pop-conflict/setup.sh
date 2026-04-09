#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
printf 'original\n' > a.txt
git add a.txt
GIT_AUTHOR_DATE='@1699999990 +0000' \
GIT_COMMITTER_DATE='@1699999990 +0000' \
git commit -m initial >/dev/null
