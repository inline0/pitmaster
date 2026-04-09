#!/bin/bash
set -e

export GIT_AUTHOR_NAME="Test User"
export GIT_AUTHOR_EMAIL="test@pitmaster.dev"
export GIT_AUTHOR_DATE="2024-01-15T00:00:10+0000"
export GIT_COMMITTER_NAME="Test User"
export GIT_COMMITTER_EMAIL="test@pitmaster.dev"
export GIT_COMMITTER_DATE="2024-01-15T00:00:10+0000"

git init --initial-branch=main --object-format=sha256 . >/dev/null
printf 'tracked\n' > tracked.txt
git add tracked.txt
git commit -m "Initial commit" >/dev/null
git tag -a v1 -m "Release 1" >/dev/null
