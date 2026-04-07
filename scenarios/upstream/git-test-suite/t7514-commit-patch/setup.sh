#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo line1 >file
git add file 2>/dev/null || true
git commit -m commit1 2>/dev/null || true
echo more >>file
echo e | env GIT_EDITOR=": >editor_was_started" git commit -p -m commit2 file
echo more >>file
echo e | env GIT_EDITOR=": >editor_was_started" git commit -p -m commit3 file
