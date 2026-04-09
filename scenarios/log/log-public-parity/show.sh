#!/usr/bin/env bash
set -euo pipefail

rev="${1:-HEAD}"

printf 'hash=%s\n' "$(git show --format=%H --no-patch "$rev")"
printf 'subject=%s\n' "$(git show --format=%s --no-patch "$rev")"
git show --format= --name-only --no-renames "$rev" | sed '/^$/d' | sort | sed 's/^/path=/'
