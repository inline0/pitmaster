#!/usr/bin/env bash
set -euo pipefail

git init --initial-branch=main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

printf 'hello world\n' > article.txt
git add article.txt
GIT_AUTHOR_DATE='@1700500000 +0000' \
GIT_COMMITTER_DATE='@1700500000 +0000' \
git commit -m base >/dev/null

printf 'hello planet\n' > article.txt
git add article.txt
GIT_AUTHOR_DATE='@1700500001 +0000' \
GIT_COMMITTER_DATE='@1700500001 +0000' \
git commit -m second >/dev/null
