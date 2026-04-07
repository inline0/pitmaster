#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init -b main repo 2>/dev/null || true
test_commit A 2>/dev/null || true
test_commit B 2>/dev/null || true
test_commit C 2>/dev/null || true
git reset --hard HEAD~ 2>/dev/null || true
cp -R repo copy 2>/dev/null || true

true
