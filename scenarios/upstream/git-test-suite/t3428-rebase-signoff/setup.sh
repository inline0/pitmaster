#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m "Initial empty commit" 2>/dev/null || true
git checkout -b conflict-branch first 2>/dev/null || true
git config alias.rbs "rebase --signoff" 2>/dev/null || true
git checkout --theirs file 2>/dev/null || true
git add file 2>/dev/null || true
git commit --amend -m "first" 2>/dev/null || true
git checkout first 2>/dev/null || true
git checkout --theirs file 2>/dev/null || true
git add file 2>/dev/null || true
echo a >a
git add a 2>/dev/null || true
git checkout --ours file 2>/dev/null || true
echo b >a
git add a file 2>/dev/null || true
echo c >a
git add a 2>/dev/null || true
