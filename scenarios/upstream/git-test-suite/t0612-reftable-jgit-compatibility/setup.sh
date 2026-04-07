#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init repo 2>/dev/null || true
test_commit A 2>/dev/null || true
touch file 2>/dev/null || true
printf "%s HEAD@{0}: commit (initial): initial commit" "$(git rev-parse --short HEAD)" >expect 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit A 2>/dev/null || true
test_commit C 2>/dev/null || true

true
