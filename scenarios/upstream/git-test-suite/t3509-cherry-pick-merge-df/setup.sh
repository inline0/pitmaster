#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir a 2>/dev/null || true
git add a 2>/dev/null || true
git commit -m a 2>/dev/null || true
mkdir b 2>/dev/null || true
git commit -m b 2>/dev/null || true
git checkout -b branch 2>/dev/null || true
rm b/a 2>/dev/null || true
git mv a b/a 2>/dev/null || true
git commit -m swap 2>/dev/null || true
git add f1 2>/dev/null || true
git commit -m f1 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout main^0 2>/dev/null || true
git cherry-pick branch 2>/dev/null || true
git checkout --orphan nick-testcase 2>/dev/null || true
git rm -rf . 2>/dev/null || true
git add empty 2>/dev/null || true
git commit -m "Empty file" 2>/dev/null || true
git checkout -b simple 2>/dev/null || true
mv empty file 2>/dev/null || true
mkdir empty 2>/dev/null || true
mv file empty 2>/dev/null || true
git add empty/file 2>/dev/null || true
git commit -m "Empty file under empty dir" 2>/dev/null || true
echo content >newfile 2>/dev/null || true
git add newfile 2>/dev/null || true
git commit -m "New file" 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout -q nick-testcase^0 2>/dev/null || true
git cherry-pick --strategy=resolve simple 2>/dev/null || true

true
