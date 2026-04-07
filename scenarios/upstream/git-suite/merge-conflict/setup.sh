#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo -e "line 1\nline 2\nline 3" > file.txt
git add file.txt
git commit -m "base"
git checkout -b feature
echo -e "line 1\nfeature change\nline 3" > file.txt
git add file.txt
git commit -m "feature"
git checkout main
echo -e "line 1\nmain change\nline 3" > file.txt
git add file.txt
git commit -m "main"
git merge feature --no-edit 2>/dev/null || true
git add file.txt
git commit --no-edit -m "resolved merge" 2>/dev/null || true
