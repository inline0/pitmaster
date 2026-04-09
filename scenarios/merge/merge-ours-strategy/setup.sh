#!/usr/bin/env bash
set -euo pipefail

export GIT_AUTHOR_DATE="2024-01-15T00:00:10+0000"
export GIT_COMMITTER_DATE="2024-01-15T00:00:10+0000"

git init -q --initial-branch=main
git config user.email test@pitmaster.dev
git config user.name "Test User"

printf 'base\n' > shared.txt
git add shared.txt
git commit -q -m "base"

git checkout -q -b feature
printf 'feature\n' > feature.txt
git add feature.txt
git commit -q -m "feature"

git checkout -q main
printf 'main\n' > main.txt
git add main.txt
git commit -q -m "main"
