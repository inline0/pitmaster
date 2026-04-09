#!/usr/bin/env bash
set -euo pipefail

git init --initial-branch=main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

for i in 1 2 3 4; do
  printf 'commit %s\n' "$i" > history.txt
  git add history.txt
  GIT_AUTHOR_DATE="@170010000${i} +0000" \
  GIT_COMMITTER_DATE="@170010000${i} +0000" \
  git commit -m "commit ${i}" >/dev/null
done

git commit-graph write --reachable >/dev/null
