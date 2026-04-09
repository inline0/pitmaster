#!/usr/bin/env bash
set -euo pipefail

head="$(git rev-parse HEAD)"
rm ".git/objects/${head:0:2}/${head:2}"

if git cat-file -p "$head" >/dev/null 2>&1; then
    printf 'missing-object=no\n' > .error-state
else
    printf 'missing-object=yes\n' > .error-state
fi
