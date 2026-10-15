#!/usr/bin/env bash
set -euo pipefail
git init -b main >/dev/null
git config user.name 'Test User'
git config user.email test@pitmaster.dev
export GIT_AUTHOR_DATE='2026-10-15T10:00:00+0000'
export GIT_COMMITTER_DATE='2026-10-15T10:00:00+0000'
printf 'base\n' > unrelated.txt
git add unrelated.txt
git commit -m base >/dev/null
printf 'leave unstaged\n' > unrelated.txt
base=$(printf 'base\n' | git hash-object -w --stdin)
ours=$(printf 'ours\n' | git hash-object -w --stdin)
theirs=$(printf 'theirs\n' | git hash-object -w --stdin)
for path in resolved.txt deleted.txt; do
  printf '100644 %s 1\t%s\n100644 %s 2\t%s\n100644 %s 3\t%s\n' \
    "$base" "$path" "$ours" "$path" "$theirs" "$path" | git update-index --index-info
done
printf 'resolved\n' > resolved.txt
