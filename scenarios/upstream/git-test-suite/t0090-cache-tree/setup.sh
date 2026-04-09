#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit foo 2>/dev/null || true
git read-tree HEAD 2>/dev/null || true
echo "I changed this file" >foo 2>/dev/null || true
git add foo 2>/dev/null || true

true
