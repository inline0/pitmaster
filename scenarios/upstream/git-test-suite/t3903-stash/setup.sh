#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo 1 >expect 2>/dev/null || true
echo 4 >other-file 2>/dev/null || true
git stash apply 2>/dev/null || true
echo 3 >expect 2>/dev/null || true
git reset --hard 2>/dev/null || true
echo 4 >file 2>/dev/null || true
echo 4 >expect 2>/dev/null || true
git reset --hard 2>/dev/null || true
echo 5 >other-file 2>/dev/null || true
git add other-file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m other-file 2>/dev/null || true
git stash apply 2>/dev/null || true

true
