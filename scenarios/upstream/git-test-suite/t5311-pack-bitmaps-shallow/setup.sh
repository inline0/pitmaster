#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config pack.writeBitmapLookupTable '"$writeLookupTable"' 2>/dev/null || true
echo 1 >file
git add file 2>/dev/null || true
git commit -m orig 2>/dev/null || true
echo 2 >file
git commit -a -m update 2>/dev/null || true
echo 1 >file
git commit -a -m repeat 2>/dev/null || true
