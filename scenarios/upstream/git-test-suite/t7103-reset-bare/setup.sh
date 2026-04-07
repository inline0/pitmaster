#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo one >file
git add file 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo two >file
git commit -a -m two 2>/dev/null || true
mkdir worktree
echo one >expect
