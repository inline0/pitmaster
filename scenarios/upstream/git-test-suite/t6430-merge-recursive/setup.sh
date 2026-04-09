#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo hello >a 2>/dev/null || true
cp a b 2>/dev/null || true
cp a c 2>/dev/null || true
mkdir d 2>/dev/null || true
cp a d/e 2>/dev/null || true
test_tick 2>/dev/null || true
git add a b c d/e 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git branch side 2>/dev/null || true
git branch df-1 2>/dev/null || true
git branch df-2 2>/dev/null || true
git branch df-3 2>/dev/null || true
git branch remove 2>/dev/null || true
git branch submod 2>/dev/null || true
git branch copy 2>/dev/null || true
git branch rename 2>/dev/null || true
git branch rename-ln 2>/dev/null || true
echo hello >>a 2>/dev/null || true
cp a d/e 2>/dev/null || true
git add a d/e 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "main modifies a and d/e" 2>/dev/null || true
git checkout side 2>/dev/null || true
echo goodbye >>a 2>/dev/null || true
git add a 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "side modifies a" 2>/dev/null || true
git checkout df-1 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "df-1 makes b/c" 2>/dev/null || true

true
