#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit first file 2>/dev/null || true
test_commit second file 2>/dev/null || true
test_commit third file 2>/dev/null || true
git rebase HEAD~1 -x "echo exec touch F >>$todo" 2>/dev/null || true
git rebase HEAD -x "true" 2>output 2>/dev/null || true

true
