#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mkdir dir 2>/dev/null || true
echo parent > dir/foo 2>/dev/null || true
echo dummy > bar 2>/dev/null || true
echo committed > HEAD 2>/dev/null || true
git add bar dir/foo HEAD 2>/dev/null || true
git commit -m initial 2>/dev/null || true
test_tick 2>/dev/null || true
test_commit second dir/foo head 2>/dev/null || true
echo index > dir/foo 2>/dev/null || true
git add dir/foo 2>/dev/null || true
test_write_lines n n n | test_must_fail git stash save -p 2>/dev/null || true
test_write_lines y n y | git stash save -p 2>/dev/null || true
git reset --hard 2>/dev/null || true
git stash apply 2>/dev/null || true

true
