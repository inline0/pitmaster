#!/usr/bin/env bash
set -euo pipefail

git init --initial-branch=main source >/dev/null 2>&1
git -C source config user.email test@example.com
git -C source config user.name Test
printf 'bare parity\n' > source/README.md
git -C source add README.md
GIT_AUTHOR_DATE='2024-01-22T00:00:00+0000' \
GIT_COMMITTER_DATE='2024-01-22T00:00:00+0000' \
git -C source commit -m "initial" >/dev/null 2>&1
git -C source checkout -b feature >/dev/null 2>&1
printf 'feature\n' > source/feature.txt
git -C source add feature.txt
GIT_AUTHOR_DATE='2024-01-22T00:00:01+0000' \
GIT_COMMITTER_DATE='2024-01-22T00:00:01+0000' \
git -C source commit -m "feature" >/dev/null 2>&1
git -C source checkout main >/dev/null 2>&1
git clone --bare source bare.git >/dev/null 2>&1

cp -R bare.git/. .
rm -rf source bare.git
