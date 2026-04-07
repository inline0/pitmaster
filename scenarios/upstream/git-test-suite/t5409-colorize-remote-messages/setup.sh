#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo 1 >file
git add file 2>/dev/null || true
git commit -m 1 2>/dev/null || true
