#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-09T00:00:04+0000"
export GIT_COMMITTER_DATE="2024-01-09T00:00:04+0000"

cat > tracked.txt <<'EOF'
this file is tracked
EOF

git add tracked.txt
git commit -m "Initial commit"

cat > untracked.txt <<'EOF'
this file is not tracked
EOF
