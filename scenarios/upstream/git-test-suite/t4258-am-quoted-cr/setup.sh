#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_write_lines one two three >text 2>/dev/null || true
test_commit one text 2>/dev/null || true
test_write_lines one owt three >text 2>/dev/null || true
test_commit two text 2>/dev/null || true
git reset --hard one 2>/dev/null || true
git reset --hard one 2>/dev/null || true

true
