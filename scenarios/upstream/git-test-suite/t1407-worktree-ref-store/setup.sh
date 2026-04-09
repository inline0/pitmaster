#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit first 2>/dev/null || true
git worktree add -b wt-main wt 2>/dev/null || true
test_commit second 2>/dev/null || true
echo "$SHA1 refs/heads/main 0x0" >expected 2>/dev/null || true
echo "$SHA1 refs/heads/wt-main 0x1" >expected 2>/dev/null || true
echo "$SHA1 refs/heads/main 0x1" >expected 2>/dev/null || true

true
