#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit --no-tag one 2>/dev/null || true
test_commit --no-tag two 2>/dev/null || true
git reset --hard HEAD^ 2>/dev/null || true
test_commit --no-tag three 2>/dev/null || true
test_commit --no-tag four 2>/dev/null || true
git reset --hard HEAD^ 2>/dev/null || true
test_commit unpacked 2>/dev/null || true

true
