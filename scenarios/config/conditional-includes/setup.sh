#!/usr/bin/env bash
set -euo pipefail
git init -b main >/dev/null
git config user.name 'Test User'
git config user.email test@pitmaster.dev
export GIT_AUTHOR_DATE='2026-10-15T10:00:00+0000'
export GIT_COMMITTER_DATE='2026-10-15T10:00:00+0000'
printf 'base\n' > file.txt
git add file.txt
git commit -m base >/dev/null
cat > settings.conf <<'EOF'
[demo]
value = before
[includeIf "worktree:**"]
path = matched.inc
[includeIf "onbranch:absent"]
path = missing.inc
[demo]
value = after
EOF
cat > matched.inc <<'EOF'
[demo]
value = matched
EOF
git config include.path ../settings.conf
