#!/usr/bin/env bash
set -euo pipefail

export GIT_AUTHOR_DATE="2024-01-15T00:00:10+0000"
export GIT_COMMITTER_DATE="2024-01-15T00:00:10+0000"

git merge feature1 feature2 -m "Merge branches 'feature1', 'feature2'" >/dev/null
