#!/usr/bin/env bash
set -euo pipefail

export GIT_AUTHOR_DATE="2024-01-06T00:00:03+0000"
export GIT_COMMITTER_DATE="2024-01-06T00:00:03+0000"
git merge rename >/dev/null 2>&1 || true
