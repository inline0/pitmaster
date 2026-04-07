#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git checkout -f main 2>/dev/null || true
git reset --hard first 2>/dev/null || true
test_tick 2>/dev/null || true
git cherry-pick first..fourth 2>/dev/null || true
git checkout -f first 2>/dev/null || true
test_commit one 2>/dev/null || true
test_commit two 2>/dev/null || true
test_commit three 2>/dev/null || true
git checkout -f main 2>/dev/null || true
git reset --hard first 2>/dev/null || true
git cherry-pick three one two 2>/dev/null || true
git checkout -f main 2>/dev/null || true
git reset --hard first 2>/dev/null || true

true
