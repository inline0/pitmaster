#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo 1 >preimage
printf "%s\n" 1 2 >postimage
echo 3 >other
git commit --allow-empty -m basis 2>/dev/null || true
mkdir -p sub/dir/b
mkdir -p objects
git add file sub/dir/file sub/dir/b/file objects/file 2>/dev/null || true
