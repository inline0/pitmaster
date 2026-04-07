#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config rebase.autosquash true 2>/dev/null || true
git config rebase.autosquash false 2>/dev/null || true
git config rebase.autosquash true 2>/dev/null || true
git config rebase.autosquash false 2>/dev/null || true
git reset --hard base 2>/dev/null || true
echo 1 >file1 2>/dev/null || true
git add -u 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "squash! forst" 2>/dev/null || true
git tag final-missquash 2>/dev/null || true
test_tick 2>/dev/null || true
git rebase --autosquash -i HEAD^^^ 2>/dev/null || true
git reset --hard base 2>/dev/null || true
echo 4 >file4 2>/dev/null || true
git add file4 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "first new commit" 2>/dev/null || true
echo 1 >file1 2>/dev/null || true
git add -u 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "squash! first" -m "extra para for first" 2>/dev/null || true
git tag final-multisquash 2>/dev/null || true
test_tick 2>/dev/null || true
git rebase --autosquash -i HEAD~4 2>/dev/null || true
echo 1 >expect 2>/dev/null || true
git reset --hard base 2>/dev/null || true
echo 1 >file1 2>/dev/null || true
git add -u 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "squash! third" 2>/dev/null || true
echo 4 >file4 2>/dev/null || true
git add file4 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "third commit" 2>/dev/null || true
git tag final-presquash 2>/dev/null || true
test_tick 2>/dev/null || true
git rebase --autosquash -i HEAD~4 2>/dev/null || true
echo 0 >expect 2>/dev/null || true
echo 1 >expect 2>/dev/null || true
git reset --hard base 2>/dev/null || true
echo 1 >file1 2>/dev/null || true
git add -u 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "squash! $oid" -m "extra para" 2>/dev/null || true
git tag final-shasquash 2>/dev/null || true
test_tick 2>/dev/null || true
git rebase --autosquash -i HEAD^^^ 2>/dev/null || true
echo 1 >expect 2>/dev/null || true

true
