#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo .gitignore >.gitignore
echo actual >>.gitignore
echo expect >>.gitignore
mkdir dir
echo x >dir/file1
echo y >dir/file2
git add dir 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git tag initial 2>/dev/null || true
echo "?? symlink" >expect
mkdir copy
echo "changed in copy" >copy/file2
git add copy 2>/dev/null || true
git commit -m second 2>/dev/null || true
echo " D copy/file1" >expect
echo " D copy/file2" >>expect
echo "?? copy" >>expect
