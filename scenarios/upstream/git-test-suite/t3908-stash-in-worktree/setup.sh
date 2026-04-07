#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit initial 2>/dev/null || true
git worktree add wt 2>/dev/null || true
test_commit -C wt in-worktree 2>/dev/null || true
mkdir wt/subdir 2>/dev/null || true
echo modified >../initial.t 2>/dev/null || true
git stash 2>/dev/null || true
git stash apply >out 2>/dev/null || true

true
