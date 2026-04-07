#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
printf "line %d\n" 1 2 3 >file1
git add file1 file2 2>/dev/null || true
git commit -m "Initial commit" 2>/dev/null || true
git tag initial 2>/dev/null || true
echo line 4 >>file1
git commit -m "add line 4 to file1" file1 2>/dev/null || true
git tag second 2>/dev/null || true
echo "line 5" >> file1
git add file1 2>/dev/null || true
echo "line 5" >> file2
git add file2 2>/dev/null || true
echo "line 5" >> file1
git add file1 2>/dev/null || true
echo "line 4" >> file2
git add file2 2>/dev/null || true
echo "line 4" >> file2
git add file2 2>/dev/null || true
echo "line 5" >> file1
git commit -m "add line 5" file1 2>/dev/null || true
echo "line 5" >> file1
git commit -m "add line 5" file1 2>/dev/null || true
git branch branch1 2>/dev/null || true
git branch branch2 2>/dev/null || true
git branch branch3 2>/dev/null || true
git checkout branch1 2>/dev/null || true
echo "line 5 in branch1" >> file1
git commit -a -m "change in branch1" 2>/dev/null || true
git checkout branch2 2>/dev/null || true
echo "line 5 in branch2" >> file1
git commit -a -m "change in branch2" 2>/dev/null || true
git tag third 2>/dev/null || true
git checkout branch3 2>/dev/null || true
echo a new file >file3
git add file3 2>/dev/null || true
git commit -a -m "change in branch3" 2>/dev/null || true
git checkout third 2>/dev/null || true
