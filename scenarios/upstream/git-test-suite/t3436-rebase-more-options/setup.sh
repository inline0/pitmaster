#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git checkout -b topic 2>/dev/null || true
test_write_lines "line 1" "	line 2" "line 3" >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m "add file" 2>/dev/null || true
test_write_lines "line 1" "new line 2" "line 3" >file 2>/dev/null || true
git commit -am "update file" 2>/dev/null || true
git tag side 2>/dev/null || true
test_commit commit1 foo foo1 2>/dev/null || true
test_commit commit2 foo foo2 2>/dev/null || true
test_commit commit3 foo foo3 2>/dev/null || true
git checkout --orphan main 2>/dev/null || true
test_write_lines "line 1" "        line 2" "line 3" >file 2>/dev/null || true
git commit -am "add file" 2>/dev/null || true
git tag main 2>/dev/null || true
mkdir test-bin 2>/dev/null || true
git rebase --abort 2>/dev/null || true
git rebase --apply --ignore-whitespace main side 2>/dev/null || true
git rebase --abort 2>/dev/null || true
git rebase --merge --ignore-whitespace main side 2>/dev/null || true

true
