#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit initial file1 2>/dev/null || true
test_commit change1 file1 2>/dev/null || true
git branch -m branch1 2>/dev/null || true
git symbolic-ref refs/heads/a-branch "$origin" 2>/dev/null || true
git checkout -f a-branch 2>/dev/null || true
git checkout -f a-branch 2>/dev/null || true
git checkout branch1 2>/dev/null || true

true
