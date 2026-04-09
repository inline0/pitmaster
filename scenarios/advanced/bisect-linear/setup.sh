#!/usr/bin/env bash
set -euo pipefail

export GIT_AUTHOR_DATE="2024-01-17T00:00:10+0000"
export GIT_COMMITTER_DATE="2024-01-17T00:00:10+0000"

git init -q --initial-branch=main
git config user.email test@pitmaster.dev
git config user.name "Test User"

for i in 1 2 3 4 5 6; do
  printf 'version %s\n' "$i" > file.txt
  git add file.txt
  git commit -q -m "c$i"
done

git tag good "$(git rev-list --max-parents=0 HEAD)"
git tag bad HEAD
