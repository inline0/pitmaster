#!/usr/bin/env bash
set -euo pipefail

rev="${1:-HEAD}"
type="$(git cat-file -t "$rev" 2>/dev/null || true)"

if [ "$type" = "tag" ]; then
  peeled="$(git rev-list -n 1 "$rev")"
  printf 'hash=%s\n' "$peeled"
  printf 'subject=%s\n' "$(git show --format=%s --no-patch "$peeled")"
  printf 'tag=%s\n' "${rev#refs/tags/}"
  git show --format= --name-only --no-renames "$peeled" | sed '/^$/d' | sort | sed 's/^/path=/'
  exit 0
fi

printf 'hash=%s\n' "$(git show --format=%H --no-patch "$rev")"
printf 'subject=%s\n' "$(git show --format=%s --no-patch "$rev")"
git show --format= --name-only --no-renames "$rev" | sed '/^$/d' | sort | sed 's/^/path=/'
