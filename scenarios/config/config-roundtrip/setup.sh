#!/usr/bin/env bash
set -euo pipefail

git init --initial-branch=main >/dev/null 2>&1
git config user.email test@example.com
git config user.name Test
git config core.filemode false
git config core.logAllRefUpdates true
git config alias.lg "log --oneline"
git config remote.origin.url https://example.com/repo.git
git config --add remote.origin.fetch +refs/heads/*:refs/remotes/origin/*
git config --add remote.origin.fetch ^refs/heads/tmp
git config branch.main.merge refs/heads/main

printf 'config parity\n' > README.md
git add README.md
GIT_AUTHOR_DATE='2024-01-20T00:00:00+0000' \
GIT_COMMITTER_DATE='2024-01-20T00:00:00+0000' \
git commit -m "initial" >/dev/null 2>&1
