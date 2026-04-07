#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo First > A 2>/dev/null || true
git update-index --add A 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Add A." 2>/dev/null || true
git checkout -b my-topic-branch 2>/dev/null || true
echo Second > B 2>/dev/null || true
git update-index --add B 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Add B." 2>/dev/null || true
echo AnotherSecond > C 2>/dev/null || true
git update-index --add C 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Add C." 2>/dev/null || true
git checkout -f main 2>/dev/null || true
echo Third >> A 2>/dev/null || true
git update-index A 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Modify A." 2>/dev/null || true
git cherry-pick my-topic-branch^0 2>/dev/null || true

true
