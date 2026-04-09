#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
git config core.logAllRefUpdates true
export GIT_AUTHOR_DATE="2024-01-04T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-04T00:00:00+0000"

cat > a.txt <<'EOF'
line 1
line 2
line 3
EOF

git add a.txt
git commit -m base >/dev/null

git checkout -b feature >/dev/null

cat > a.txt <<'EOF'
line 1
feature change
line 3
EOF

git add a.txt
export GIT_AUTHOR_DATE="2024-01-04T00:00:01+0000"
export GIT_COMMITTER_DATE="2024-01-04T00:00:01+0000"
git commit -m feature >/dev/null

pick_id=$(git rev-parse HEAD)

git checkout main >/dev/null

cat > a.txt <<'EOF'
line 1
main change
line 3
EOF

git add a.txt
export GIT_AUTHOR_DATE="2024-01-04T00:00:02+0000"
export GIT_COMMITTER_DATE="2024-01-04T00:00:02+0000"
git commit -m main >/dev/null

printf '%s\n' "$pick_id" > .pick-id
