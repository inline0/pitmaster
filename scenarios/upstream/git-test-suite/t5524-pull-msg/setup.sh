#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo added >bfile
git add bfile 2>/dev/null || true
git commit -m "add bfile" 2>/dev/null || true
echo "second" >afile
git add afile 2>/dev/null || true
git commit -m "second commit" 2>/dev/null || true
echo "original $dollar" >afile
git add afile 2>/dev/null || true
git commit -m "do not clobber $dollar signs" 2>/dev/null || true
