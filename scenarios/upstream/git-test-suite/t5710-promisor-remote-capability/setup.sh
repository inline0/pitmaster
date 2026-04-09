#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init template 2>/dev/null || true
test_commit -C template 1 2>/dev/null || true
test_commit -C template 2 2>/dev/null || true
test_commit -C template 3 2>/dev/null || true
mv server/objects/pack/pack-* . 2>/dev/null || true

true
