#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo file >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -a -m original 2>/dev/null || true
test_tick 2>/dev/null || true
echo changed >file 2>/dev/null || true
git commit -a -m changed 2>/dev/null || true
git hash-object -w file 2>/dev/null || true

true
