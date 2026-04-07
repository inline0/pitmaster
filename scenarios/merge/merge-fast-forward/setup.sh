#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

cat > file.txt <<'EOF'
initial content
EOF

git add file.txt
git commit -m "Initial commit"

git checkout -b feature

cat > feature.txt <<'EOF'
feature content
EOF

git add feature.txt
git commit -m "Add feature file on feature branch"

git checkout main
