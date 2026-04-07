#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git add a 2>/dev/null || true
test_tick && git commit -m A 2>/dev/null || true
git branch A 2>/dev/null || true
git branch B 2>/dev/null || true
git branch C 2>/dev/null || true
git branch D 2>/dev/null || true
git branch E 2>/dev/null || true
git branch F 2>/dev/null || true
git checkout B 2>/dev/null || true
echo b >b 2>/dev/null || true
echo 11 >>a 2>/dev/null || true
git add a b 2>/dev/null || true
test_tick && git commit -m B 2>/dev/null || true
git checkout C 2>/dev/null || true
echo c >c 2>/dev/null || true
git add c 2>/dev/null || true
test_tick && git commit -m C 2>/dev/null || true
git checkout D 2>/dev/null || true
echo d >d 2>/dev/null || true
git add a d 2>/dev/null || true
test_tick && git commit -m D 2>/dev/null || true
git checkout E 2>/dev/null || true
mkdir subdir 2>/dev/null || true
git mv a subdir/a 2>/dev/null || true
echo e >subdir/e 2>/dev/null || true
git add subdir 2>/dev/null || true
test_tick && git commit -m E 2>/dev/null || true
git checkout F 2>/dev/null || true
test_tick && git commit --allow-empty -m F 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout A^0 2>/dev/null || true
touch random_file && git add random_file 2>/dev/null || true
git merge E^0 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout A^0 2>/dev/null || true
mkdir subdir 2>/dev/null || true
touch subdir/e 2>/dev/null || true
git add subdir/e 2>/dev/null || true

true
