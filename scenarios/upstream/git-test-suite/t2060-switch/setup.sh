#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit first 2>/dev/null || true
git branch first-branch 2>/dev/null || true
test_commit second 2>/dev/null || true
test_commit third 2>/dev/null || true
git update-ref refs/remotes/origin/foo first-branch 2>/dev/null || true
git switch first-branch 2>/dev/null || true

true
