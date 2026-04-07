#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit init 2>/dev/null || true
mkdir sub 2>/dev/null || true
touch ./1 ./2 sub/1 sub/2 2>/dev/null || true
git add 1 2 sub/1 sub/2 2>/dev/null || true
git update-index --skip-worktree 1 sub/1 2>/dev/null || true
git update-index 1 2>/dev/null || true
git update-index 1 2>/dev/null || true

true
