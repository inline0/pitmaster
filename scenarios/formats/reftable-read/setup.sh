#!/usr/bin/env bash
set -euo pipefail

git init --initial-branch=main --ref-format=reftable >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

printf 'tracked\n' > tracked.txt
git add tracked.txt
GIT_AUTHOR_DATE='@1700300000 +0000' \
GIT_COMMITTER_DATE='@1700300000 +0000' \
git commit -m initial >/dev/null

git branch feature
GIT_AUTHOR_DATE='@1700300001 +0000' \
GIT_COMMITTER_DATE='@1700300001 +0000' \
git tag -a v1 -m "Release 1" >/dev/null
