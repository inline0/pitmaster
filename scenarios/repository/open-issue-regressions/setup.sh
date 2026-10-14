#!/usr/bin/env bash
set -euo pipefail
git init --initial-branch=main >/dev/null 2>&1
git config user.name Test
git config user.email test@example.com
git config core.logallrefupdates true
mkdir docs
printf 'a\n' > a.txt
printf 'x\n' > docs/x.txt
git add .
GIT_AUTHOR_DATE='2024-01-01T00:00:00+0000' GIT_COMMITTER_DATE='2024-01-01T00:00:00+0000' git commit -m initial >/dev/null
for i in 1 2 3 4 5 6 7 8; do
    printf '%s\n' "$i" > y.txt
    git add y.txt
    GIT_AUTHOR_DATE="2024-01-01T00:00:0${i}+0000" GIT_COMMITTER_DATE="2024-01-01T00:00:0${i}+0000" git commit -m "y$i" >/dev/null
done
git branch old HEAD~1
git update-index --index-version 4
