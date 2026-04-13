#!/usr/bin/env bash
set -euo pipefail

cat > a.txt <<'EOF'
line1
resolved
EOF

git add a.txt
git rerere >/dev/null

hash=$(find .git/rr-cache -mindepth 1 -maxdepth 1 -type d | head -n 1 | xargs basename)
printf '%s\n' "$hash" > .hash.txt
