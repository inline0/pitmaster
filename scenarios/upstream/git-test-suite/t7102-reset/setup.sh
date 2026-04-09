#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_tick 2>/dev/null || true
echo "1st file" >first 2>/dev/null || true
git add first 2>/dev/null || true
git commit -m "create 1st file" 2>/dev/null || true
echo "2nd file" >second 2>/dev/null || true
git add second 2>/dev/null || true
git commit -m "create 2nd file" 2>/dev/null || true
echo "2nd line 1st file" >>first 2>/dev/null || true
git commit -a -m "modify 1st file" 2>/dev/null || true
git rm first 2>/dev/null || true
git mv second secondfile 2>/dev/null || true
git commit -a -m "remove 1st and rename 2nd" 2>/dev/null || true
echo "1st line 2nd file" >secondfile 2>/dev/null || true
echo "2nd line 2nd file" >>secondfile 2>/dev/null || true
git reset --hard >.actual 2>/dev/null || true
echo HEAD is now at $hex $(commit_msg) >.expected 2>/dev/null || true

true
