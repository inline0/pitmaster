#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config core.logAllRefUpdates 0 2>/dev/null || true
git add file1 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo 1 > file1
echo 2 > file2
git add file1 file2 2>/dev/null || true
git commit -m second 2>/dev/null || true
echo 3 > file3
git add file3 2>/dev/null || true
