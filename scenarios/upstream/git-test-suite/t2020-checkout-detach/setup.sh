#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit one 2>/dev/null || true
test_commit two 2>/dev/null || true
test_commit three && git tag -d three 2>/dev/null || true
test_commit four && git tag -d four 2>/dev/null || true
git branch branch 2>/dev/null || true
git tag tag 2>/dev/null || true
git checkout branch 2>/dev/null || true
cat .git/HEAD >expect 2>/dev/null || true
git checkout $opt 2>/dev/null || true
cat .git/HEAD >actual 2>/dev/null || true

true
