#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git tag c0 2>/dev/null || true
echo second >file
git add file 2>/dev/null || true
git commit -m second 2>/dev/null || true
git tag c1 2>/dev/null || true
git branch test 2>/dev/null || true
echo third >file
git add file 2>/dev/null || true
git commit -m third 2>/dev/null || true
git tag c2 2>/dev/null || true
git merge -s recursive c0 2>/dev/null || true
git merge -s recursive c1 2>/dev/null || true
git merge -s ours c0 2>/dev/null || true
git merge -s ours c1 2>/dev/null || true
git merge -s subtree c0 2>/dev/null || true
git merge c1 c2 2>/dev/null || true
