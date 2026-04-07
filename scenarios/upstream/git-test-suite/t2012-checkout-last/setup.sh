#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit initial world hello 2>/dev/null || true
git branch other 2>/dev/null || true
test_commit --append second world "hello again" 2>/dev/null || true
git checkout other 2>/dev/null || true

true
