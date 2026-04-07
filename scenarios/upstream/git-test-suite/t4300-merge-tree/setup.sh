#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git reset --hard initial 2>/dev/null || true
test_commit "add-a-not-b" "ONE" "AAA" 2>/dev/null || true
git merge-tree initial initial add-a-not-b >actual 2>/dev/null || true
cat >expected <<EXPECTED 2>/dev/null || true
git reset --hard initial 2>/dev/null || true
test_commit "add-not-a-b" "ONE" "AAA" 2>/dev/null || true
git merge-tree initial add-not-a-b initial >actual 2>/dev/null || true
git reset --hard initial 2>/dev/null || true
test_commit "add-a-b-same-A" "ONE" "AAA" 2>/dev/null || true
git reset --hard initial 2>/dev/null || true
test_commit "add-a-b-same-B" "ONE" "AAA" 2>/dev/null || true
git merge-tree initial add-a-b-same-A add-a-b-same-B >actual 2>/dev/null || true

true
