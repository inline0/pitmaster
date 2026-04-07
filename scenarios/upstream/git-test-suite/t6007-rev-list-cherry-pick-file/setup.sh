#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git symbolic-ref HEAD refs/heads/independent 2>/dev/null || true
rm .git/index 2>/dev/null || true
echo Hallo > foo 2>/dev/null || true
git add foo 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "independent" 2>/dev/null || true
echo Bello > foo 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "independent, too" foo 2>/dev/null || true
git checkout -b dup-orig 2>/dev/null || true
test_commit dup-base 2>/dev/null || true
git revert dup-base 2>/dev/null || true
git cherry-pick dup-base 2>/dev/null || true
git checkout -b dup-side HEAD~3 2>/dev/null || true
test_tick 2>/dev/null || true
git cherry-pick -3 dup-orig 2>/dev/null || true
git checkout -b shy-diff 2>/dev/null || true
test_commit dont-look-at-me 2>/dev/null || true
echo Hello >dont-look-at-me.t 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m tip dont-look-at-me.t 2>/dev/null || true
git checkout -b mainline HEAD^ 2>/dev/null || true
test_commit to-cherry-pick 2>/dev/null || true

true
