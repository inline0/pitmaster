#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
mkdir subdir
echo file >subdir/file
git add subdir/file 2>/dev/null || true
git commit -q -m file 2>/dev/null || true
git config receive.denyCurrentBranch warn 2>/dev/null || true
git config receive.denyCurrentBranch warn 2>/dev/null || true
echo real >subdir/file
git commit -m real subdir/file 2>/dev/null || true
echo link >subdir/file
git commit -m link subdir/file 2>/dev/null || true
