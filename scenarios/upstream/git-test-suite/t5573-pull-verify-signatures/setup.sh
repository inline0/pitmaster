#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo 1 >a && git add a 2>/dev/null || true
test_tick && git commit -m initial 2>/dev/null || true
git tag initial 2>/dev/null || true
echo 2 >b && git add b 2>/dev/null || true
test_tick && git commit -S -m "signed" 2>/dev/null || true
echo 3 >c && git add c 2>/dev/null || true
test_tick && git commit -m "unsigned" 2>/dev/null || true
echo 4 >d && git add d 2>/dev/null || true
test_tick && git commit -S -m "bad" 2>/dev/null || true
git hash-object -w -t commit forged >forged.commit 2>/dev/null || true
git checkout $(cat forged.commit) 2>/dev/null || true
echo 5 >e && git add e 2>/dev/null || true
test_tick && git commit -SB7227189 -m "untrusted" 2>/dev/null || true

true
