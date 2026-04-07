#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo 1 >file && git add file 2>/dev/null || true
test_tick && git commit -S -m initial 2>/dev/null || true
git tag initial 2>/dev/null || true
git branch side 2>/dev/null || true
echo 2 >file && test_tick && git commit -a -S -m second 2>/dev/null || true
git tag second 2>/dev/null || true
git checkout side 2>/dev/null || true
echo 3 >elif && git add elif 2>/dev/null || true
test_tick && git commit -m "third on side" 2>/dev/null || true
git checkout main 2>/dev/null || true
test_tick && git merge -S side 2>/dev/null || true
git tag merge 2>/dev/null || true
echo 4 >file && test_tick && git commit -a -m "fourth unsigned" 2>/dev/null || true
git tag fourth-unsigned 2>/dev/null || true
test_tick && git commit --amend -S -m "fourth signed" 2>/dev/null || true
git tag fourth-signed 2>/dev/null || true
git config commit.gpgsign true 2>/dev/null || true
echo 5 >file && test_tick && git commit -a -m "fifth signed" 2>/dev/null || true
git tag fifth-signed 2>/dev/null || true
git config commit.gpgsign false 2>/dev/null || true
echo 6 >file && test_tick && git commit -a -m "sixth" 2>/dev/null || true
git tag sixth-unsigned 2>/dev/null || true
git config commit.gpgsign true 2>/dev/null || true
echo 7 >file && test_tick && git commit -a -m "seventh" --no-gpg-sign 2>/dev/null || true
git tag seventh-unsigned 2>/dev/null || true
test_tick && git rebase -f HEAD^^ && git tag sixth-signed HEAD^ 2>/dev/null || true
git tag seventh-signed 2>/dev/null || true
echo 8 >file && test_tick && git commit -a -m eighth -SB7227189 2>/dev/null || true
git tag eighth-signed-alt 2>/dev/null || true
echo 9 | git commit-tree HEAD^{tree} >oid 2>/dev/null || true
git tag ninth-unsigned $(cat oid) 2>/dev/null || true
echo 10 | git commit-tree -S HEAD^{tree} >oid 2>/dev/null || true
git tag tenth-signed $(cat oid) 2>/dev/null || true
echo 11 | git commit-tree --gpg-sign HEAD^{tree} >oid 2>/dev/null || true
git tag eleventh-signed $(cat oid) 2>/dev/null || true
echo 12 | git commit-tree --gpg-sign=B7227189 HEAD^{tree} >oid 2>/dev/null || true
git tag twelfth-signed-alt $(cat oid) 2>/dev/null || true

true
