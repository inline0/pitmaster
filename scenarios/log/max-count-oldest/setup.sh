#!/usr/bin/env bash
set -euo pipefail
git init --initial-branch=main >/dev/null 2>&1
git config user.email test@example.com
git config user.name "Test User"
mkdir -p app docs
for i in 1 2 3 4 5; do
    if (( i % 2 )); then path=app/main.txt; else path=docs/guide.txt; fi
    printf 'version %s\n' "$i" > "$path"
    git add "$path"
    GIT_AUTHOR_DATE="2024-02-01T00:00:0${i}+0000" \
    GIT_COMMITTER_DATE="2024-02-01T00:00:0${i}+0000" \
    git commit -m "Update $i" >/dev/null 2>&1
done
