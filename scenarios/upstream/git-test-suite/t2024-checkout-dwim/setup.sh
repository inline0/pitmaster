#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit my_main 2>/dev/null || true
git init repo_a 2>/dev/null || true
test_commit a_main 2>/dev/null || true
git checkout -b foo 2>/dev/null || true
test_commit a_foo 2>/dev/null || true
git checkout -b bar 2>/dev/null || true
test_commit a_bar 2>/dev/null || true
git checkout -b ambiguous_branch_and_file 2>/dev/null || true
test_commit a_ambiguous_branch_and_file 2>/dev/null || true
git init repo_b 2>/dev/null || true
test_commit b_main 2>/dev/null || true
git checkout -b foo 2>/dev/null || true
test_commit b_foo 2>/dev/null || true
git checkout -b baz 2>/dev/null || true
test_commit b_baz 2>/dev/null || true
git checkout -b ambiguous_branch_and_file 2>/dev/null || true
test_commit b_ambiguous_branch_and_file 2>/dev/null || true
git config remote.repo_b.fetch \ 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -B main 2>/dev/null || true

true
