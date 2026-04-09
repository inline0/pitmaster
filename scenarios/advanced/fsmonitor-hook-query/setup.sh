#!/usr/bin/env bash
set -euo pipefail

export GIT_AUTHOR_DATE="2024-01-16T00:00:10+0000"
export GIT_COMMITTER_DATE="2024-01-16T00:00:10+0000"

git init -q
git config user.email test@pitmaster.dev
git config user.name "Test User"

mkdir -p nested
printf 'tracked\n' > tracked.txt
printf 'nested\n' > nested/file.txt
git add tracked.txt nested/file.txt
git commit -q -m "initial"

cat > .git/hooks/query-fsmonitor <<'SH'
#!/usr/bin/env bash
printf '%s|%s\n' "$1" "$2" >> .git/fsmonitor.log
printf 'git-token\0tracked.txt\0nested/file.txt\0'
SH

chmod +x .git/hooks/query-fsmonitor
git config core.fsmonitor .git/hooks/query-fsmonitor
