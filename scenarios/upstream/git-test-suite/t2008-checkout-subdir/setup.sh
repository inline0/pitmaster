#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo "base" > file0
git add file0 2>/dev/null || true
mkdir dir1
echo "hello" > dir1/file1
git add dir1/file1 2>/dev/null || true
mkdir dir2
echo "bonjour" > dir2/file2
git add dir2/file2 2>/dev/null || true
git commit -m "populate tree" 2>/dev/null || true
git checkout HEAD -- ../file0 2>/dev/null || true
git checkout HEAD -- ../dir2/file2 2>/dev/null || true
git checkout HEAD -- .. 2>/dev/null || true
git checkout HEAD -- file0 2>/dev/null || true
git checkout HEAD -- dir1 2>/dev/null || true
git checkout HEAD -- dir1/file1 2>/dev/null || true
git checkout HEAD -- ../dir1/../dir1/file1 2>/dev/null || true
