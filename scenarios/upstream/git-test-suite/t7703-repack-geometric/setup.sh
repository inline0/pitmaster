#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init geometric 2>/dev/null || true
git init geometric 2>/dev/null || true
test_commit "base" 2>/dev/null || true
git init geometric 2>/dev/null || true
test_commit_bulk --start=1 1 && # 3 objects 2>/dev/null || true
test_commit_bulk --start=2 2 && # 6 objects 2>/dev/null || true
test_commit_bulk --start=4 4 && # 12 objects 2>/dev/null || true

true
