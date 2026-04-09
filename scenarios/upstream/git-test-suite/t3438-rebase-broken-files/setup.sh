#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit base file 2>/dev/null || true
git checkout -b branch1 2>/dev/null || true
test_commit one file 2>/dev/null || true
git checkout -b branch2 HEAD^ 2>/dev/null || true
test_commit two file 2>/dev/null || true
git checkout -b side HEAD^ 2>/dev/null || true
test_commit unrelated 2>/dev/null || true

true
