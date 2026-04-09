#!/usr/bin/env bash
set -euo pipefail

git init --initial-branch=main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
git config gc.auto 0

printf 'base\n' > tracked.txt
git add tracked.txt
GIT_AUTHOR_DATE='@1700200000 +0000' \
GIT_COMMITTER_DATE='@1700200000 +0000' \
git commit -m base >/dev/null
git rev-list --all | git pack-objects .git/objects/pack/pack-one --revs >/dev/null

printf 'changed\n' > tracked.txt
printf 'extra\n' > extra.txt
git add tracked.txt extra.txt
GIT_AUTHOR_DATE='@1700200001 +0000' \
GIT_COMMITTER_DATE='@1700200001 +0000' \
git commit -m second >/dev/null
git rev-list --all | git pack-objects .git/objects/pack/pack-two --revs >/dev/null
git multi-pack-index write >/dev/null
