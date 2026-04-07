#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

T=$(git mktree </dev/null) 2>/dev/null || true
MB=$(git merge-base G H) 2>/dev/null || true
MB=$(git merge-base --all G H) 2>/dev/null || true
MB=$(git show-branch --merge-base G H) 2>/dev/null || true
MB=$(git merge-base PL PR) 2>/dev/null || true
MB=$(git merge-base --all PL PR) 2>/dev/null || true
echo $IH >expected 2>/dev/null || true
git merge-base --independent IB IH >actual 2>/dev/null || true
test_commit MMR 2>/dev/null || true
test_commit MM1 2>/dev/null || true
test_commit MM-o 2>/dev/null || true
test_commit MM-p 2>/dev/null || true
test_commit MM-q 2>/dev/null || true
test_commit MMA 2>/dev/null || true
git checkout MM1 2>/dev/null || true
test_commit MM-r 2>/dev/null || true
test_commit MM-s 2>/dev/null || true
test_commit MM-t 2>/dev/null || true
test_commit MMB 2>/dev/null || true
git checkout MMR 2>/dev/null || true
test_commit MM-u 2>/dev/null || true
test_commit MM-v 2>/dev/null || true
test_commit MM-w 2>/dev/null || true
test_commit MM-x 2>/dev/null || true
test_commit MMC 2>/dev/null || true

true
