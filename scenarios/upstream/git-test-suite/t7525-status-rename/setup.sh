#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo 1 >original
git add . 2>/dev/null || true
git commit -m"Adding original file." 2>/dev/null || true
echo 2 >> renamed
git add . 2>/dev/null || true
git add copy 2>/dev/null || true
