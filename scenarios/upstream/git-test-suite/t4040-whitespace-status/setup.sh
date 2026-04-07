#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
mkdir a b
git add . 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo " " >a/d
git commit -a -m second 2>/dev/null || true
echo "  " >a/d
echo " " >b/e
git add a/d 2>/dev/null || true
echo x >>b/e
git commit -a -m "worktree state" 2>/dev/null || true
