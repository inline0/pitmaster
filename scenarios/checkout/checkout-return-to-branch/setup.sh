#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
git config core.logAllRefUpdates true
export GIT_AUTHOR_DATE="2024-01-18T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-18T00:00:00+0000"

cat > a.txt <<'EOF'
one
EOF

git add a.txt
git commit -m first >/dev/null
git rev-parse HEAD > .detach-id

cat > a.txt <<'EOF'
two
EOF

git add a.txt
export GIT_AUTHOR_DATE="2024-01-18T00:00:01+0000"
export GIT_COMMITTER_DATE="2024-01-18T00:00:01+0000"
git commit -m second >/dev/null
