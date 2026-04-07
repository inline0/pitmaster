#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit 1 A 2>/dev/null || true
test_commit 2 A 2>/dev/null || true
git symbolic-ref HEAD refs/heads/other 2>/dev/null || true
test_commit 3 B 2>/dev/null || true
test_commit 1b A 1 2>/dev/null || true
test_commit 4 B 2>/dev/null || true
git checkout -B fail other 2>/dev/null || true
echo "$1,$2" >.git/PRE-REBASE-INPUT 2>/dev/null || true

true
