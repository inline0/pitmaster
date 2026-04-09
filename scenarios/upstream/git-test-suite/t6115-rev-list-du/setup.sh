#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit --no-tag one 2>/dev/null || true
test_commit --no-tag two 2>/dev/null || true
git reset --hard HEAD^ 2>/dev/null || true
test_commit --no-tag three 2>/dev/null || true
test_commit --no-tag four 2>/dev/null || true
git reset --hard HEAD^ 2>/dev/null || true
test_commit unpacked 2>/dev/null || true

true
