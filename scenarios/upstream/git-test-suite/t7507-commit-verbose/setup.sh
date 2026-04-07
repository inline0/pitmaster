#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo content >file
git add file 2>/dev/null || true
git commit -F message 2>/dev/null || true
git commit --amend -v 2>/dev/null || true
echo content modified >file
git add file 2>/dev/null || true
git commit -F message 2>/dev/null || true
git commit --amend -v 2>/dev/null || true
git config diff.mnemonicprefix true 2>/dev/null || true
git commit --amend -v 2>/dev/null || true
git commit --amend -F diff 2>/dev/null || true
git commit --amend -F diff -v 2>/dev/null || true
git config diff.submodule log 2>/dev/null || true
git commit -m "sub added" 2>/dev/null || true
echo "more" >>file
git commit -a -m "submodule commit" 2>/dev/null || true
echo dirty >file
