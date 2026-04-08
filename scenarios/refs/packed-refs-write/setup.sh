#!/bin/bash
set -e

git init -b main .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

export GIT_AUTHOR_DATE="2024-01-07T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-07T00:00:00+0000"

cat > file.txt <<'EOF'
initial content
EOF

git add file.txt
git commit -m "Initial commit"

git branch feature-one
git branch feature-two

git tag v1.0
git tag -a v1.1 -m "Annotated tag v1.1"

export GIT_AUTHOR_DATE="2024-01-08T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-08T00:00:00+0000"

cat > file.txt <<'EOF'
updated content
EOF

git add file.txt
git commit -m "Second commit"

git tag v2.0
