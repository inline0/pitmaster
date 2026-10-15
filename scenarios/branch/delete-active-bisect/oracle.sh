#!/usr/bin/env bash
set -euo pipefail
if git branch -D main >/dev/null 2>&1; then
    printf 'deleted\n' > .branch-protection
else
    printf 'protected\n' > .branch-protection
fi
