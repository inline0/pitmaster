#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
mkdir dir
echo parent > dir/foo
echo dummy > bar
echo committed > HEAD
git add bar dir/foo HEAD 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo index > dir/foo
git add dir/foo 2>/dev/null || true
git stash apply 2>/dev/null || true
git stash apply --index 2>/dev/null || true
git stash apply --index 2>/dev/null || true
git add test 2>/dev/null || true
git commit -m "initial" 2>/dev/null || true
git stash -p 2>error 2>/dev/null || true
echo to-stash >test
echo index >test
git add test 2>/dev/null || true
echo working-tree >test
