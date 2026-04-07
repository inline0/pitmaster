#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init otherrepo 2>/dev/null || true
echo 1 >file 2>/dev/null || true
git add file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git tag -a -m msg1 t1 2>/dev/null || true
git checkout -b b1 2>/dev/null || true
echo 2 >file 2>/dev/null || true
git commit -m "second b1" file 2>/dev/null || true
echo 3 >file 2>/dev/null || true
git commit -m "third b1" file 2>/dev/null || true
git tag -a -m msg2 t2 2>/dev/null || true
git checkout -b b2 main 2>/dev/null || true
echo 0 >file 2>/dev/null || true
git commit -m "second b2" file 2>/dev/null || true
echo 00 >file 2>/dev/null || true
git commit -m "another b2" file 2>/dev/null || true
echo 000 >file 2>/dev/null || true
git commit -m "yet another b2" file 2>/dev/null || true
mkdir ignored_dir 2>/dev/null || true
echo "ignored_dir/" >>.gitignore 2>/dev/null || true
git checkout main 2>/dev/null || true
printf " (main)" >expected 2>/dev/null || true
printf " (main)" >expected 2>/dev/null || true
git checkout main 2>/dev/null || true

true
