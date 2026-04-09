#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init no_merge_base 2>/dev/null || true
(
cd no_merge_base 2>/dev/null || true
git checkout -b L 2>/dev/null || true
test_commit A content A 2>/dev/null || true
git checkout --orphan R 2>/dev/null || true
test_commit B content B 2>/dev/null || true
)
(
cd no_merge_base 2>/dev/null || true
git checkout L^0 2>/dev/null || true
)
git init unique_merge_base 2>/dev/null || true
(
cd unique_merge_base 2>/dev/null || true
test_commit base content "1 2>/dev/null || true
git branch L 2>/dev/null || true
git branch R 2>/dev/null || true
git checkout L 2>/dev/null || true
test_commit L content "1 2>/dev/null || true
git checkout R 2>/dev/null || true
git rm content 2>/dev/null || true
test_commit R renamed "1 2>/dev/null || true
)
(
cd unique_merge_base 2>/dev/null || true
git checkout L^0 2>/dev/null || true
MAIN=$(git rev-parse --short main) 2>/dev/null || true
)
git init multiple_merge_bases 2>/dev/null || true
(
cd multiple_merge_bases 2>/dev/null || true
test_commit initial content "1 2>/dev/null || true
git branch L 2>/dev/null || true
git branch R 2>/dev/null || true
git checkout L 2>/dev/null || true
test_commit L1 content "0 2>/dev/null || true
git checkout R 2>/dev/null || true
test_commit R1 content "1 2>/dev/null || true
git checkout L 2>/dev/null || true
git merge R1 2>/dev/null || true
git checkout R 2>/dev/null || true
git merge L1 2>/dev/null || true
git checkout L 2>/dev/null || true
test_commit L3 content "0 2>/dev/null || true
git checkout R 2>/dev/null || true
git rm content 2>/dev/null || true
test_commit R3 renamed "0 2>/dev/null || true
)

true
