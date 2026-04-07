#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit init 2>/dev/null || true
echo modified >> init.t 2>/dev/null || true
touch added 2>/dev/null || true
git add init.t added 2>/dev/null || true
git commit -m "modified and added" 2>/dev/null || true
git tag top 2>/dev/null || true
git checkout -f top 2>/dev/null || true
git update-index --skip-worktree init.t 2>/dev/null || true
git read-tree -m -u HEAD^ 2>/dev/null || true
echo init > expected 2>/dev/null || true
git checkout -f top 2>/dev/null || true
git update-index --skip-worktree init.t 2>/dev/null || true
echo dirty >> init.t 2>/dev/null || true
git update-index --no-skip-worktree init.t 2>/dev/null || true

true
