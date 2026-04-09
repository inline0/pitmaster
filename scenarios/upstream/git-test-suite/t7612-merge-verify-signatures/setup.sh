#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo 1 >file && git add file 2>/dev/null || true
test_tick && git commit -m initial 2>/dev/null || true
git tag initial 2>/dev/null || true
git checkout -b side-signed 2>/dev/null || true
echo 3 >elif && git add elif 2>/dev/null || true
test_tick && git commit -S -m "signed on side" 2>/dev/null || true
git checkout initial 2>/dev/null || true
git checkout -b side-unsigned 2>/dev/null || true
echo 3 >foo && git add foo 2>/dev/null || true
test_tick && git commit -m "unsigned on side" 2>/dev/null || true
git checkout initial 2>/dev/null || true
git checkout -b side-bad 2>/dev/null || true
echo 3 >bar && git add bar 2>/dev/null || true
test_tick && git commit -S -m "bad on side" 2>/dev/null || true
git hash-object -w -t commit forged >forged.commit 2>/dev/null || true
git checkout initial 2>/dev/null || true
git checkout -b side-untrusted 2>/dev/null || true
echo 3 >baz && git add baz 2>/dev/null || true
test_tick && git commit -SB7227189 -m "untrusted on side" 2>/dev/null || true
git checkout main 2>/dev/null || true

true
