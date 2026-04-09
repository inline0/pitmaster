#!/usr/bin/env bash
set -euo pipefail

git checkout left >/dev/null
export GIT_AUTHOR_DATE="2024-01-07T00:00:07+0000"
export GIT_COMMITTER_DATE="2024-01-07T00:00:07+0000"
git merge right >/dev/null
