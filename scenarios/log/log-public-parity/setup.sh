#!/usr/bin/env bash
set -euo pipefail

git init --initial-branch=main >/dev/null 2>&1
git config user.email test@example.com
git config user.name "Test User"

mkdir -p docs
printf 'initial\n' > README.md
printf 'guide v1\n' > docs/guide.txt
git add README.md docs/guide.txt
GIT_AUTHOR_DATE='2024-01-21T00:00:00+0000' \
GIT_COMMITTER_DATE='2024-01-21T00:00:00+0000' \
git commit -m "Initial commit" >/dev/null 2>&1

git checkout -b feature >/dev/null 2>&1
printf 'feature work\n' > feature.txt
git add feature.txt
GIT_AUTHOR_DATE='2024-01-21T00:00:01+0000' \
GIT_COMMITTER_DATE='2024-01-21T00:00:01+0000' \
git commit -m "Feature branch work" >/dev/null 2>&1

git checkout main >/dev/null 2>&1
printf 'guide v2\n' > docs/guide.txt
printf 'initial\nmain update\n' > README.md
git add README.md docs/guide.txt
GIT_AUTHOR_DATE='2024-01-21T00:00:02+0000' \
GIT_COMMITTER_DATE='2024-01-21T00:00:02+0000' \
git commit -m "Main branch update" >/dev/null 2>&1

GIT_AUTHOR_DATE='2024-01-21T00:00:03+0000' \
GIT_COMMITTER_DATE='2024-01-21T00:00:03+0000' \
git tag -a v1 -m "Release 1" >/dev/null 2>&1
