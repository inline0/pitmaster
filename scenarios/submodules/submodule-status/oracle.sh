#!/usr/bin/env bash
set -euo pipefail

status_line=$(git submodule status --cached)
expected=$(printf '%s\n' "$status_line" | awk '{print substr($1,1)}' | tr -d ' +-')
actual=$(git -C vendor/lib rev-parse HEAD)
printf 'vendor/lib|./dep\n' > .submodule-list.txt
printf '%s|%s|false|vendor/lib\n' "$expected" "$actual" > .submodule-status.txt
