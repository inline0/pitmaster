#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

printf "one\n" > app.txt
git add app.txt
export GIT_AUTHOR_DATE="2024-01-03T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-03T00:00:00+0000"
git commit -m "First commit"

printf "two\n" > app.txt
git add app.txt
export GIT_AUTHOR_DATE="2024-01-03T00:00:01+0000"
export GIT_COMMITTER_DATE="2024-01-03T00:00:01+0000"
git commit -m "Second commit"
