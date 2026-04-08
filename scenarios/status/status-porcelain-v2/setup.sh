#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-10T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-10T00:00:00+0000"

cat > staged.txt <<'EOF'
base staged
EOF

cat > modified.txt <<'EOF'
base modified
EOF

git add staged.txt modified.txt
git commit -m "Initial commit"

cat > staged.txt <<'EOF'
new staged
EOF

git add staged.txt

cat > modified.txt <<'EOF'
new modified
EOF

cat > untracked.txt <<'EOF'
untracked
EOF
