#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init outer-repo 2>/dev/null || true
git init --bare --initial-branch=main outer-repo/bare-repo 2>/dev/null || true
test_commit A 2>/dev/null || true

true
