#!/usr/bin/env bash
set -euo pipefail

export GIT_AUTHOR_DATE="2024-01-15T00:00:10+0000"
export GIT_COMMITTER_DATE="2024-01-15T00:00:10+0000"

git init -q --initial-branch=main
git config user.email test@pitmaster.dev
git config user.name "Test User"

printf 'base\n' > base.txt
git add base.txt
git commit -q -m "base"
base="$(git rev-parse HEAD)"

git checkout -q -b feature1
printf 'a\n' > a.txt
git add a.txt
git commit -q -m "feature1"

git checkout -q main
printf 'main\n' > main.txt
git add main.txt
git commit -q -m "main"

git checkout -q -b feature2 "$base"
printf 'b\n' > b.txt
git add b.txt
git commit -q -m "feature2"

git checkout -q main
