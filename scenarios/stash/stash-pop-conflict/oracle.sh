#!/usr/bin/env bash
set -euo pipefail

printf 'stashed\n' > a.txt
GIT_AUTHOR_DATE='@1700000020 +0000' \
GIT_COMMITTER_DATE='@1700000020 +0000' \
git stash push -m 'conflict stash' >/dev/null
printf 'current\n' > a.txt
git add a.txt
git commit -m Current >/dev/null
git stash pop >/dev/null 2>&1 || true

git stash list > .stash-list.txt
cat a.txt > .worktree.txt
