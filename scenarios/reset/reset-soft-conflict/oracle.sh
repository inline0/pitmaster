#!/usr/bin/env bash
set -euo pipefail

git cherry-pick "$(cat .pick-id)" >/dev/null 2>&1 || true

if git reset --soft HEAD >/dev/null 2>&1; then
    printf 'allowed\n' > .reset-result.txt
    exit 1
fi

printf 'blocked\n' > .reset-result.txt
