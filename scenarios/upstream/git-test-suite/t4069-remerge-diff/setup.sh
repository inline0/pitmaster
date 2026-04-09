#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_write_lines 1 2 3 4 5 6 7 8 9 >numbers 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m base 2>/dev/null || true
git branch feature_a 2>/dev/null || true
git branch feature_b 2>/dev/null || true
git branch feature_c 2>/dev/null || true
git branch ab_resolution 2>/dev/null || true
git branch bc_resolution 2>/dev/null || true
git checkout feature_a 2>/dev/null || true
test_write_lines 1 2 three 4 5 6 7 eight 9 >numbers 2>/dev/null || true
git commit -a -m change_a 2>/dev/null || true
git checkout feature_b 2>/dev/null || true
test_write_lines 1 2 tres 4 5 6 7 8 9 >numbers 2>/dev/null || true
git commit -a -m change_b 2>/dev/null || true
git checkout feature_c 2>/dev/null || true
test_write_lines 1 2 3 4 5 6 7 8 9 10 >numbers 2>/dev/null || true
git commit -a -m change_c 2>/dev/null || true
git checkout bc_resolution 2>/dev/null || true
git merge --ff-only feature_b 2>/dev/null || true
git merge feature_c 2>/dev/null || true
git checkout ab_resolution 2>/dev/null || true
git merge --ff-only feature_a 2>/dev/null || true
test_write_lines 1 2 drei 4 5 6 7 acht 9 >numbers 2>/dev/null || true
git add numbers 2>/dev/null || true
git merge --continue 2>/dev/null || true

true
