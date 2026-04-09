#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit main file-1 test 2>/dev/null || true
git checkout -b stuff 2>/dev/null || true
test_commit feature_a file-2 aaa 2>/dev/null || true
test_commit feature_b file-2 ddd 2>/dev/null || true
git checkout stuff^0 2>/dev/null || true
git rebase -i -v main 2>/dev/null || true
git checkout stuff^0 2>/dev/null || true
git rebase -i -v main 2>/dev/null || true
git checkout --theirs file-2 2>/dev/null || true
git add file-2 2>/dev/null || true

true
