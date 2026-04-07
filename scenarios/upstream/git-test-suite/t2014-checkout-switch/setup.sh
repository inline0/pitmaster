#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo Hello >file
git add file 2>/dev/null || true
git commit -m V1 2>/dev/null || true
echo Hello world >file
git add file 2>/dev/null || true
git checkout -b other 2>/dev/null || true
git commit -m V2 2>/dev/null || true
