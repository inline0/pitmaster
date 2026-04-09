#!/usr/bin/env bash
set -euo pipefail

GIT_AUTHOR_DATE='@1700000010 +0000' \
GIT_COMMITTER_DATE='@1700000010 +0000' \
git stash push -m 'staged only' >/dev/null

git stash apply >/dev/null
git status --porcelain=v2 > .status.txt
cat a.txt > .worktree.txt
