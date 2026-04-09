#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit initial file 2>/dev/null || true
test_commit first file 2>/dev/null || true
git checkout initial 2>/dev/null || true
git mv file file2 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m renamed-file 2>/dev/null || true
git tag renamed-file 2>/dev/null || true
git checkout -b side initial 2>/dev/null || true
test_commit side1 file 2>/dev/null || true
test_commit side2 file 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout renamed-file 2>/dev/null || true

true
