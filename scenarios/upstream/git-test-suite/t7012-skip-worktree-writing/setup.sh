#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo modified >> init.t
git add init.t added 2>/dev/null || true
git commit -m "modified and added" 2>/dev/null || true
git tag top 2>/dev/null || true
git checkout -f top 2>/dev/null || true
echo init > expected
git checkout -f top 2>/dev/null || true
echo dirty >> init.t
git checkout -f top 2>/dev/null || true
git checkout -f top 2>/dev/null || true
echo dirty >> added
echo dirty > 1
echo "100644 $EMPTY_BLOB 0	1" > expected
echo dirty > expected
git checkout -f init 2>/dev/null || true
mkdir sub
git add 1 2 sub/1 sub/2 2>/dev/null || true
mkdir subdir
echo A >subdir/A
echo untouched >untouched
echo removeme >removeme
echo modified >modified
git add . 2>/dev/null || true
git commit -m Initial 2>/dev/null || true
echo AA >>subdir/A
echo addme >addme
echo tweaked >>modified
git add addme 2>/dev/null || true
git stash push 2>/dev/null || true
echo in the way >modified
echo in the way >expect
