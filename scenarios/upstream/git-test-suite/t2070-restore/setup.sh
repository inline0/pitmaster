#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit first 2>/dev/null || true
echo first-and-a-half >>first.t 2>/dev/null || true
git add first.t 2>/dev/null || true
test_commit second 2>/dev/null || true
echo one >one 2>/dev/null || true
echo two >two 2>/dev/null || true
echo untracked >untracked 2>/dev/null || true
echo ignored >ignored 2>/dev/null || true
echo /ignored >.gitignore 2>/dev/null || true
git add one two .gitignore 2>/dev/null || true
git update-ref refs/heads/one main 2>/dev/null || true
cat one >expected 2>/dev/null || true
echo dirty >>one 2>/dev/null || true
git restore one 2>/dev/null || true

true
