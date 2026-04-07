#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit A 2>/dev/null || true
test_commit --notick B 2>/dev/null || true
git checkout -b branch B 2>/dev/null || true
test_commit D 2>/dev/null || true
mkdir dir 2>/dev/null || true
test_commit dir/D 2>/dev/null || true
test_commit E 2>/dev/null || true
git checkout main 2>/dev/null || true
test_commit C 2>/dev/null || true
git checkout branch 2>/dev/null || true
git merge C 2>/dev/null || true
git tag F 2>/dev/null || true
test_commit G 2>/dev/null || true
test_commit H 2>/dev/null || true

true
