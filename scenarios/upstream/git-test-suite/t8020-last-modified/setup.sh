#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit 1 file 2>/dev/null || true
mkdir a 2>/dev/null || true
test_commit 2 a/file 2>/dev/null || true
git tag -mA t2 2 2>/dev/null || true
mkdir a/b 2>/dev/null || true
test_commit 3 a/b/file 2>/dev/null || true

true
