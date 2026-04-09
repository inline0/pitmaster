#!/usr/bin/env bash
set -euo pipefail

git init -b main >/dev/null
git config user.email test@pitmaster.dev
git config user.name "Test User"
cat > tracked.txt <<'EOF'
author env parity
EOF
git add tracked.txt
