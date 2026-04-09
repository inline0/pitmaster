#!/usr/bin/env bash
set -euo pipefail

GIT_AUTHOR_DATE='@1700000010 +0000' \
GIT_COMMITTER_DATE='@1700000010 +0000' \
bash -lc '
printf "staged\n" > a.txt
git add a.txt
printf "worktree\n" > a.txt
git stash push -m "apply stash" >/dev/null
git stash apply >/dev/null
git status --porcelain=v2 > .status.txt
git stash list > .stash-list.txt
cat a.txt > .worktree.txt
'
