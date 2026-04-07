#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo to-be-gone >deleted
echo original >modified
echo now-a-file >symlink
git add . 2>/dev/null || true
git commit -m base 2>/dev/null || true
git tag base 2>/dev/null || true
echo now-here >added
echo new >modified
mkdir subdir
echo content >subdir/file
git add -A . 2>/dev/null || true
git commit -m new 2>/dev/null || true
git tag new 2>/dev/null || true
echo "usage: working without -z is not supported" >expect
echo "fatal: tree objects not supported" >expect
echo "usage: pathspec arguments not supported" >expect
printf "\0" >>expect
