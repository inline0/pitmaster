#!/bin/bash
set -e

# Create source repo in a subdir, then shallow clone into current dir
mkdir source
cd source
git init .
git config user.email "test@test.com"
git config user.name "Test"
for i in 1 2 3 4 5; do echo "commit $i" > file.txt; git add file.txt; git commit -m "commit $i"; done
cd ..

# Clone shallow into a temp, then move .git into current dir
git clone --depth=2 source shallow-tmp
rm -rf .git
mv shallow-tmp/.git .git
mv shallow-tmp/* . 2>/dev/null || true
rm -rf shallow-tmp source
