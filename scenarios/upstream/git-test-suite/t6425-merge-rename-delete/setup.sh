#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo foo >A 2>/dev/null || true
git add A 2>/dev/null || true
git commit -m "initial" 2>/dev/null || true
git checkout -b rename 2>/dev/null || true
git mv A B 2>/dev/null || true
git commit -m "rename" 2>/dev/null || true
git checkout main 2>/dev/null || true
git rm A 2>/dev/null || true
git commit -m "delete" 2>/dev/null || true

true
