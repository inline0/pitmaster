#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git tag -a -m never never-fetch-tag HEAD 2>/dev/null || true
git branch bogus/fetched HEAD~1 2>/dev/null || true
git branch bogus/ignore HEAD 2>/dev/null || true
git checkout bogus/fetched 2>/dev/null || true
test_commit extra 2>/dev/null || true

true
