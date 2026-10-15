#!/usr/bin/env bash
set -euo pipefail
git init --initial-branch=main >/dev/null 2>&1
git config user.name Test
git config user.email test@example.com
for i in 1 2 3 4 5; do
    printf '%s\n' "$i" > file.txt
    git add file.txt
    GIT_AUTHOR_DATE="2024-03-01T00:00:0${i}+0000" \
    GIT_COMMITTER_DATE="2024-03-01T00:00:0${i}+0000" \
    git commit -m "Commit $i" >/dev/null 2>&1
done
git bisect start main HEAD~3 >/dev/null
