#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

rm -f trace 2>/dev/null || true
test_create_repo server 2>/dev/null || true
test_commit -C server to_fetch 2>/dev/null || true
test_create_repo client 2>/dev/null || true
test_commit -C client we_have 2>/dev/null || true

true
