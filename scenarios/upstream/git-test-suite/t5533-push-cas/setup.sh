#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit D 2>/dev/null || true
test_commit D 2>/dev/null || true
git checkout main 2>/dev/null || true
test_commit D 2>/dev/null || true
git checkout HEAD^0 2>/dev/null || true
test_commit E 2>/dev/null || true

true
