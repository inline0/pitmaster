#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

mkdir -p src/lib docs
echo "root file" > README.md
echo "source code" > src/main.php
echo "library" > src/lib/utils.php
echo "documentation" > docs/guide.md
git add .
git commit -m "Add nested directory structure"
