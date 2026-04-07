#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit "Add A." A First First 2>/dev/null || true
git checkout -b force-3way 2>/dev/null || true
echo Dummy >Y 2>/dev/null || true
git update-index --add Y 2>/dev/null || true
git commit -m "Add Y." 2>/dev/null || true
git checkout -b filemove 2>/dev/null || true
git reset --soft main 2>/dev/null || true
mkdir D 2>/dev/null || true
git mv A D/A 2>/dev/null || true
git commit -m "Move A." 2>/dev/null || true
git checkout -b my-topic-branch main 2>/dev/null || true
test_commit "Add B." B Second Second 2>/dev/null || true
git checkout -f main 2>/dev/null || true
echo Third >>A 2>/dev/null || true
git update-index A 2>/dev/null || true
git commit -m "Modify A." 2>/dev/null || true
git checkout -b side my-topic-branch 2>/dev/null || true
echo Side >>C 2>/dev/null || true
git add C 2>/dev/null || true
git commit -m "Add C" 2>/dev/null || true
git checkout -f my-topic-branch 2>/dev/null || true
git tag topic 2>/dev/null || true
echo dirty >>A 2>/dev/null || true
git add A 2>/dev/null || true

true
