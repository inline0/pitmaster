#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config --global protocol.file.allow always 2>/dev/null || true
test_create_repo A 2>/dev/null || true
echo first >file1 2>/dev/null || true
git add file1 2>/dev/null || true
git commit -m A-initial 2>/dev/null || true
echo second >file2 2>/dev/null || true
git add file2 2>/dev/null || true
git commit -m B-addition 2>/dev/null || true

true
