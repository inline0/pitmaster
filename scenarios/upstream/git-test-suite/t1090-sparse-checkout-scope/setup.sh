#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo "initial" >a 2>/dev/null || true
echo "initial" >b 2>/dev/null || true
echo "initial" >c 2>/dev/null || true
git add a b c 2>/dev/null || true
git commit -m "initial commit" 2>/dev/null || true
git checkout -b feature 2>/dev/null || true
echo "modified" >b 2>/dev/null || true
echo "modified" >c 2>/dev/null || true
git add b c 2>/dev/null || true
git commit -m "modification" 2>/dev/null || true
git config --local --bool core.sparsecheckout true 2>/dev/null || true
mkdir .git/info 2>/dev/null || true
echo "!/*" >.git/info/sparse-checkout 2>/dev/null || true
echo "/a" >>.git/info/sparse-checkout 2>/dev/null || true
echo "/c" >>.git/info/sparse-checkout 2>/dev/null || true
git checkout main 2>/dev/null || true

true
