#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit main-one 2>/dev/null || true
test_commit main-two 2>/dev/null || true
git checkout -b upstream-branch 2>/dev/null || true
test_commit upstream-one 2>/dev/null || true
test_commit upstream-two 2>/dev/null || true
git checkout -b @/at-test 2>/dev/null || true
git checkout -b @@/at-test 2>/dev/null || true
git checkout -b @at-test 2>/dev/null || true
git checkout -b old-branch 2>/dev/null || true
test_commit old-one 2>/dev/null || true
test_commit old-two 2>/dev/null || true
git checkout -b new-branch 2>/dev/null || true
test_commit new-one 2>/dev/null || true
test_commit new-two 2>/dev/null || true
git branch -u main old-branch 2>/dev/null || true
git branch -u upstream-branch new-branch 2>/dev/null || true
git checkout old-branch 2>/dev/null || true
echo content >normal 2>/dev/null || true
echo content >fun@ny 2>/dev/null || true
git add normal fun@ny 2>/dev/null || true
git commit -m "funny path" 2>/dev/null || true

true
