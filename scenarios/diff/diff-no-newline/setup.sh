#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-12T00:00:04+0000"
export GIT_COMMITTER_DATE="2024-01-12T00:00:04+0000"

printf "no newline" > file.txt

git add file.txt
git commit -m "Initial commit without trailing newline"

printf "changed" > file.txt
