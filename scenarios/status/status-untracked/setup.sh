#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

cat > tracked.txt <<'EOF'
this file is tracked
EOF

git add tracked.txt
git commit -m "Initial commit"

cat > untracked.txt <<'EOF'
this file is not tracked
EOF
