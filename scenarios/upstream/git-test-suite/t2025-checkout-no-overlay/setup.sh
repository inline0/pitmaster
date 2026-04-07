#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m "initial" 2>/dev/null || true
mkdir dir
git add file dir/file1 2>/dev/null || true
git checkout --no-overlay HEAD -- file 2>/dev/null || true
git checkout --no-overlay HEAD -- dir/file1 2>/dev/null || true
git rm --cached file1 2>/dev/null || true
echo 1234 >file1
git checkout --theirs --no-overlay -- file1 2>/dev/null || true
mkdir subdir
git checkout --no-overlay file3-1 "*file3" 2>/dev/null || true
echo file3-1 >expect
