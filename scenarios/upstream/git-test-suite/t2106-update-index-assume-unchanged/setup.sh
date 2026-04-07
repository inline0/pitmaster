#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git branch other 2>/dev/null || true
echo upstream >file
git add file 2>/dev/null || true
git commit -m upstream 2>/dev/null || true
git checkout other 2>/dev/null || true
echo dirt >file
