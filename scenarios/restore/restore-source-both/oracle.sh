#!/usr/bin/env bash
set -euo pipefail

git restore --source=HEAD~1 --staged --worktree a.txt
