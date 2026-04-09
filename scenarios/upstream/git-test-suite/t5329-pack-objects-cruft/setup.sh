#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init repo 2>/dev/null || true
test_commit packed 2>/dev/null || true
test_commit tagged 2>/dev/null || true
git tag -a annotated -m tag 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit packed 2>/dev/null || true
test_commit old 2>/dev/null || true
test_commit new 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit packed 2>/dev/null || true
mkdir -p dir/sub 2>/dev/null || true
echo foo >foo 2>/dev/null || true
echo bar >dir/bar 2>/dev/null || true
echo baz >dir/sub/baz 2>/dev/null || true
test_tick 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "pruned" 2>/dev/null || true

true
