#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit --no-tag "Initial" base base 2>/dev/null || true
git checkout -b $b main 2>/dev/null || true
test_commit --no-tag "Change on $b" base $b || return 1 2>/dev/null || true
git checkout branch1 2>/dev/null || true

true
