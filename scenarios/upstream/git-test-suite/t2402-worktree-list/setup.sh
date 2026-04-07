#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit init 2>/dev/null || true
echo .git >expected 2>/dev/null || true
mkdir sub 2>/dev/null || true
echo ../.git >expected2 2>/dev/null || true
echo "$(git rev-parse --show-toplevel)/.git/objects" >expect 2>/dev/null || true
git worktree add --detach linked-tree main 2>/dev/null || true

true
