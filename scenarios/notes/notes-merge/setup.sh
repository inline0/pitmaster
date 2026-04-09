#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
printf 'notes parity\n' > tracked.txt
git add tracked.txt
GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git commit -m initial >/dev/null
printf 'more\n' > second.txt
git add second.txt
GIT_AUTHOR_DATE='@1700000060 +0000' \
GIT_COMMITTER_DATE='@1700000060 +0000' \
git commit -m second >/dev/null
