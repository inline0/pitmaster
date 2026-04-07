#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m "empty initial commit" 2>/dev/null || true
echo "Hello, world!" >greeting
git add greeting 2>/dev/null || true
git commit -m "add the greeting blob"  # borrowed from Git from the Bottom Up 2>/dev/null || true
git tag -m "the blob" greeting $(git rev-parse HEAD:greeting) 2>/dev/null || true
echo asdf >unrelated
git add unrelated 2>/dev/null || true
git commit -m "unrelated history" 2>/dev/null || true
git commit --allow-empty -m "another unrelated commit" 2>/dev/null || true
mkdir a
echo asdf >a/file
git add a/file 2>/dev/null || true
git commit -m "add a file in a subdirectory" 2>/dev/null || true
git commit -a -m "add sub" 2>/dev/null || true
git checkout -b boring base^ 2>/dev/null || true
echo boring >file
git add file 2>/dev/null || true
git commit -m boring 2>/dev/null || true
git checkout -b interesting base^ 2>/dev/null || true
echo interesting >file
git add file 2>/dev/null || true
git commit -m interesting 2>/dev/null || true
git checkout -B merge base 2>/dev/null || true
git merge --no-commit boring 2>/dev/null || true
echo interesting >file
git commit -am "introduce blob" 2>/dev/null || true
git checkout -B merge interesting 2>/dev/null || true
git merge --no-commit base 2>/dev/null || true
echo boring >file
git commit -am "remove blob" 2>/dev/null || true
git checkout -B merge interesting 2>/dev/null || true
git merge -m "untouched blob" base 2>/dev/null || true
