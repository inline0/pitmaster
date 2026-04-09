#!/usr/bin/env bash
set -euo pipefail

GIT_AUTHOR_DATE='@1700000000 +0000' \
GIT_COMMITTER_DATE='@1700000000 +0000' \
git stash push -m 'test stash' >/dev/null

git stash list > .stash-list.txt
git show -s --format='%s|%P' refs/stash > .stash-commit.txt
git show refs/stash:a.txt > .stash-file.txt
cat a.txt > .worktree.txt
