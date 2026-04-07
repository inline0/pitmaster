#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config gc.bigPackThreshold 2g 2>/dev/null || true
git config set --global maintenance.strategy gc 2>/dev/null || true
git gc 2>/dev/null || true
git gc 2>/dev/null || true
mkdir broken 2>/dev/null || true
(
cd broken 2>/dev/null || true
git init 2>/dev/null || true
echo "[gc] pruneexpire = CORRUPT" >>.git/config 2>/dev/null || true
)
git init remote 2>/dev/null || true
(
cd remote 2>/dev/null || true
test_commit initial 2>/dev/null || true
git branch -m develop 2>/dev/null || true
cd ../client 2>/dev/null || true
git gc 2>/dev/null || true
)

true
