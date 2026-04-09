#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init sub 2>/dev/null || true
test_commit -C sub initial 2>/dev/null || true
git init main 2>/dev/null || true
echo to-be-gone >deleted 2>/dev/null || true
echo original >modified 2>/dev/null || true
echo now-a-file >symlink 2>/dev/null || true
git add . 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m base 2>/dev/null || true
git tag base 2>/dev/null || true
git submodule add ../sub 2>/dev/null || true
echo now-here >added 2>/dev/null || true
echo new >modified 2>/dev/null || true
mkdir subdir 2>/dev/null || true
echo content >subdir/file 2>/dev/null || true
mv two-hundred renamed 2>/dev/null || true
git add -A . 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m new 2>/dev/null || true
git tag new 2>/dev/null || true

true
