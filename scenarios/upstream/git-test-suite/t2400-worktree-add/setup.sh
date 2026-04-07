#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit init 2>/dev/null || true
mkdir -p existing/subtree 2>/dev/null || true
mkdir existing_empty 2>/dev/null || true
git worktree add --detach existing_empty main 2>/dev/null || true

true
