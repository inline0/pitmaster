#!/usr/bin/env bash
set -euo pipefail

export GIT_AUTHOR_DATE="2024-01-15T00:00:10+0000"
export GIT_COMMITTER_DATE="2024-01-15T00:00:10+0000"

git rebase main >/dev/null 2>&1 || true
git rebase --abort >/dev/null
