#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git checkout main 2>/dev/null || true
git reset --hard first 2>/dev/null || true
test_tick 2>/dev/null || true
git cherry-pick --ff second 2>/dev/null || true
git checkout main 2>/dev/null || true
git reset --hard first 2>/dev/null || true
test_tick 2>/dev/null || true
git cherry-pick second 2>/dev/null || true
git checkout main 2>/dev/null || true
git reset --hard first 2>/dev/null || true
echo new line >A 2>/dev/null || true
git add A 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "add line to A" A 2>/dev/null || true
git tag A 2>/dev/null || true
git checkout -b side first 2>/dev/null || true
echo new line >B 2>/dev/null || true
git add B 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "add line to B" B 2>/dev/null || true
git tag B 2>/dev/null || true
git checkout main 2>/dev/null || true
git merge side 2>/dev/null || true
git tag C 2>/dev/null || true
git checkout -b new A 2>/dev/null || true

true
