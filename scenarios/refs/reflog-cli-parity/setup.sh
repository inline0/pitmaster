#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
git config core.logAllRefUpdates true

echo one > app.txt
git add app.txt
GIT_AUTHOR_DATE='2024-01-01T00:00:00+0000' \
GIT_COMMITTER_DATE='2024-01-01T00:00:00+0000' \
git commit -m one >/dev/null

echo two >> app.txt
git add app.txt
GIT_AUTHOR_DATE='2024-01-02T00:00:00+0000' \
GIT_COMMITTER_DATE='2024-01-02T00:00:00+0000' \
git commit -m two >/dev/null

git checkout -b topic >/dev/null
git checkout main >/dev/null
