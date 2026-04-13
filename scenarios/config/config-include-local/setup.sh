#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"

cat > .git/extra.config <<'EOF'
[core]
	editor = vim
[alias]
	lg = log --oneline
EOF

cat >> .git/config <<'EOF'
[include]
	path = extra.config
EOF

echo base > app.txt
git add app.txt
GIT_AUTHOR_DATE='2024-01-01T00:00:00+0000' \
GIT_COMMITTER_DATE='2024-01-01T00:00:00+0000' \
git commit -m base >/dev/null
