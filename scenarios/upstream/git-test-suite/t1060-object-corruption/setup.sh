#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init bit-error 2>/dev/null || true
test_commit content 2>/dev/null || true
git init no-bit-error 2>/dev/null || true
test_tick 2>/dev/null || true
test_commit content 2>/dev/null || true
git init missing 2>/dev/null || true
test_commit content 2>/dev/null || true
git init misnamed 2>/dev/null || true
test_commit content 2>/dev/null || true
mv "$bad" "$good" 2>/dev/null || true

true
