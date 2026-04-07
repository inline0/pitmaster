#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git checkout main 2>/dev/null || true
test_commit main-branch-2 file2 2 2>/dev/null || true
git checkout other-branch 2>/dev/null || true
git merge main --signoff --no-edit 2>/dev/null || true
git checkout main 2>/dev/null || true
test_commit main-branch-3 file3 3 2>/dev/null || true
git checkout other-branch 2>/dev/null || true
git merge main --no-edit 2>/dev/null || true

true
