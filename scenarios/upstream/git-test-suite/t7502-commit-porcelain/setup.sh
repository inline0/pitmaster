#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo new >file1 2>/dev/null || true
git add file1 2>/dev/null || true
echo change >>file1 2>/dev/null || true
git add file1 2>/dev/null || true
git checkout -b recursive-base 2>/dev/null || true
test_commit base file1 2>/dev/null || true
git checkout -b recursive-a recursive-base 2>/dev/null || true
test_commit commit-a file1 2>/dev/null || true
git checkout -b recursive-b recursive-base 2>/dev/null || true
test_commit commit-b file1 2>/dev/null || true
git checkout recursive-a 2>/dev/null || true
echo commit-a >file1 2>/dev/null || true
git add file1 2>/dev/null || true

true
