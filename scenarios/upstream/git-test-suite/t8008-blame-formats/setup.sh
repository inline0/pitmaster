#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo a >file 2>/dev/null || true
git add file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo b >>file 2>/dev/null || true
echo c >>file 2>/dev/null || true
echo d >>file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -a -m two 2>/dev/null || true

true
