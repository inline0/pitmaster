#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mkdir foo 2>/dev/null || true
git add foo/file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m two 2>/dev/null || true
git checkout --orphan junio-testcase 2>/dev/null || true
git rm -rf . 2>/dev/null || true
mkdir two 2>/dev/null || true
echo frotz >one 2>/dev/null || true
cp one two/three 2>/dev/null || true
git add one two/three 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m that 2>/dev/null || true
ONE=$(git rev-parse HEAD:one) 2>/dev/null || true

true
