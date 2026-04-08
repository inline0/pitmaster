#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-01T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-01T00:00:00+0000"

printf "one\n" > app.txt
git add app.txt
git commit -m "Initial commit"
git branch feature
git bundle create source.bundle --all
