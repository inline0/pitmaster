#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit -m "colliding files" 2>/dev/null || true
git tag basename_collision 2>/dev/null || true
echo "$@" >>filter.log
git rm -rf . 2>/dev/null || true
echo a >expected.log
mkdir e
git rm -rf . 2>/dev/null || true
echo "file_x filter=logger" >.gitattributes
git add .gitattributes 2>/dev/null || true
git commit -m "filter for file_x" 2>/dev/null || true
echo file_x >expected.log
