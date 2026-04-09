#!/usr/bin/env bash
set -euo pipefail

git sparse-checkout init --cone >/dev/null
git sparse-checkout set src >/dev/null
git reset --hard HEAD >/dev/null
git status --porcelain=v2 > .status.txt
find . -type f ! -path './.git/*' | sed 's#^\./##' | sort > .worktree-files.txt
