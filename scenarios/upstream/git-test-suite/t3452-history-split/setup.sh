#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init repo 2>/dev/null || true
test_commit base 2>/dev/null || true
git branch branch 2>/dev/null || true
test_commit ours 2>/dev/null || true
git switch branch 2>/dev/null || true
test_commit theirs 2>/dev/null || true
git switch - 2>/dev/null || true
git merge theirs 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit initial 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit initial 2>/dev/null || true

true
