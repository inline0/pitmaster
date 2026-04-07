#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add test 2>/dev/null || true
echo "to be deleted" >test2
git add test2 2>/dev/null || true
printf 100 >>seq
git add seq 2>/dev/null || true
git commit seq -m seq 2>/dev/null || true
