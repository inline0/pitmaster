#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init expire-to-now 2>/dev/null || true
git branch -M main 2>/dev/null || true
test_commit base 2>/dev/null || true
git checkout -b cruft 2>/dev/null || true
test_commit --no-tag cruft 2>/dev/null || true
git checkout main 2>/dev/null || true
git branch -D cruft 2>/dev/null || true
git init --bare expired.git 2>/dev/null || true
git init expire-to-5.minutes.ago 2>/dev/null || true
git branch -M main 2>/dev/null || true
test_commit base 2>/dev/null || true
git checkout -b $kind main 2>/dev/null || true
test_commit --no-tag $kind || return 1 2>/dev/null || true
git checkout main 2>/dev/null || true
git branch -D stale recent 2>/dev/null || true
git init --bare expired.git 2>/dev/null || true
git init max-cruft-size-large 2>/dev/null || true
test_commit base 2>/dev/null || true

true
