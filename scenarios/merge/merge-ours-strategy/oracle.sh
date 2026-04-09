#!/usr/bin/env bash
set -euo pipefail

export GIT_AUTHOR_DATE="2024-01-15T00:00:10+0000"
export GIT_COMMITTER_DATE="2024-01-15T00:00:10+0000"

git merge -s ours feature -m "Merge branch 'feature'" >/dev/null
