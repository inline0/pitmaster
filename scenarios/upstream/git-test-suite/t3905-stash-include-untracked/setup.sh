#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo 1 >file 2>/dev/null || true
git add file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo 2 >file 2>/dev/null || true
git add file 2>/dev/null || true
echo 3 >file 2>/dev/null || true
test_tick 2>/dev/null || true
echo 1 >file2 2>/dev/null || true
echo 1 >HEAD 2>/dev/null || true
mkdir untracked 2>/dev/null || true
echo untracked >untracked/untracked 2>/dev/null || true
git stash --include-untracked 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
cat >expect.diff <<-EOF 2>/dev/null || true
cat >expect.lstree <<-EOF 2>/dev/null || true

true
