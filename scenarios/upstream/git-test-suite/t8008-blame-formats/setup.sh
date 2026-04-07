#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo a >file
git add file 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo b >>file
echo c >>file
echo d >>file
git commit -a -m two 2>/dev/null || true
echo "This is it" >single-file
git add single-file 2>/dev/null || true
