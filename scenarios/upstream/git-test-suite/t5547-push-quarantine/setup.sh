#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init --bare dest.git 2>/dev/null || true
test_commit ok 2>/dev/null || true
test_commit reject 2>/dev/null || true

true
