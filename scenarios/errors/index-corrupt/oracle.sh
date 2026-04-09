#!/usr/bin/env bash
set -euo pipefail

printf 'not-an-index\n' > .git/index

if git status --short >/dev/null 2>&1; then
    printf 'index-corrupt=no\n' > .error-state
else
    printf 'index-corrupt=yes\n' > .error-state
fi
