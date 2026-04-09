#!/usr/bin/env bash
set -euo pipefail

git sparse-checkout init --cone >/dev/null
git sparse-checkout set src >/dev/null
git reset --hard HEAD >/dev/null
git merge feature >/dev/null
git status --porcelain=v2 > .status.txt
find . -type f ! -path './.git/*' ! -name '.status.txt' ! -name '.worktree-files.txt' | sed 's#^\./##' | sort > .worktree-files.txt
git show HEAD:docs/guide.txt > .head-docs.txt
