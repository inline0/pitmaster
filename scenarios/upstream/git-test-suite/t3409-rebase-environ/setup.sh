#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit one 2>/dev/null || true
test_commit two 2>/dev/null || true
test_commit three 2>/dev/null || true
git rebase --exec "printf %s \$GIT_DIR >environ" HEAD~1 2>/dev/null || true
git rebase --exec "printf %s \$GIT_WORK_TREE >environ" HEAD~1 2>/dev/null || true

true
