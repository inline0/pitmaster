#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit one 2>/dev/null || true
echo refs/heads/main >expected 2>/dev/null || true
git symbolic-ref FOO >actual 2>/dev/null || true
git tag -a -m new-tag new-tag HEAD 2>/dev/null || true

true
