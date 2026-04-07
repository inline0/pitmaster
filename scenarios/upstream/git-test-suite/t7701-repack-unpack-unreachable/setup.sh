#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo content > file1 2>/dev/null || true
git add . 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m initial_commit 2>/dev/null || true
git checkout -b transient_branch 2>/dev/null || true
echo more content >> file1 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -a -m more_content 2>/dev/null || true
git checkout main 2>/dev/null || true
echo even more content >> file1 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -a -m even_more_content 2>/dev/null || true
git branch -D transient_branch 2>/dev/null || true
test_tick 2>/dev/null || true
git branch transient_branch $csha1 2>/dev/null || true
git branch -D transient_branch 2>/dev/null || true
test_tick 2>/dev/null || true
ln "$packfile" "$tmppack" 2>/dev/null || true

true
