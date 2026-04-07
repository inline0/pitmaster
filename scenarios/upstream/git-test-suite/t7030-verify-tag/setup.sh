#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo 1 >file && git add file 2>/dev/null || true
test_tick && git commit -m initial 2>/dev/null || true
git tag -s -m initial initial 2>/dev/null || true
git branch side 2>/dev/null || true
echo 2 >file && test_tick && git commit -a -m second 2>/dev/null || true
git tag -s -m second second 2>/dev/null || true
git checkout side 2>/dev/null || true
echo 3 >elif && git add elif 2>/dev/null || true
test_tick && git commit -m "third on side" 2>/dev/null || true
git checkout main 2>/dev/null || true
test_tick && git merge -S side 2>/dev/null || true
git tag -s -m merge merge 2>/dev/null || true
echo 4 >file && test_tick && git commit -a -S -m "fourth unsigned" 2>/dev/null || true
git tag -a -m fourth-unsigned fourth-unsigned 2>/dev/null || true
test_tick && git commit --amend -S -m "fourth signed" 2>/dev/null || true
git tag -s -m fourth fourth-signed 2>/dev/null || true
echo 5 >file && test_tick && git commit -a -m "fifth" 2>/dev/null || true
git tag fifth-unsigned 2>/dev/null || true
git config commit.gpgsign true 2>/dev/null || true
echo 6 >file && test_tick && git commit -a -m "sixth" 2>/dev/null || true
git tag -a -m sixth sixth-unsigned 2>/dev/null || true
test_tick && git rebase -f HEAD^^ && git tag -s -m 6th sixth-signed HEAD^ 2>/dev/null || true
git tag -m seventh -s seventh-signed 2>/dev/null || true
echo 8 >file && test_tick && git commit -a -m eighth 2>/dev/null || true
git tag -uB7227189 -m eighth eighth-signed-alt 2>/dev/null || true
echo 9 >file && test_tick && git commit -a -m "ninth gpgsm-signed" 2>/dev/null || true
git tag -s -m ninth ninth-signed-x509 2>/dev/null || true

true
