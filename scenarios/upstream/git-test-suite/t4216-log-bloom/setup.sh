#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init 2>/dev/null || true
mkdir A A/B A/B/C 2>/dev/null || true
test_commit c1 A/file1 2>/dev/null || true
test_commit c2 A/B/file2 2>/dev/null || true
test_commit c3 A/B/C/file3 2>/dev/null || true
test_commit c4 A/file1 2>/dev/null || true
test_commit c5 A/B/file2 2>/dev/null || true
test_commit c6 A/B/C/file3 2>/dev/null || true
test_commit c7 A/file1 2>/dev/null || true
test_commit c8 A/B/file2 2>/dev/null || true
test_commit c9 A/B/C/file3 2>/dev/null || true
test_commit c10 file_to_be_deleted 2>/dev/null || true
git checkout -b side HEAD~4 2>/dev/null || true
test_commit side-1 file4 2>/dev/null || true
git checkout main 2>/dev/null || true
git merge side 2>/dev/null || true
test_commit c11 file5 2>/dev/null || true
mv file5 file5_renamed 2>/dev/null || true
git add file5_renamed 2>/dev/null || true
git commit -m "rename" 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "file removed" 2>/dev/null || true
git commit --allow-empty -m "empty" 2>/dev/null || true
git commit-graph write --reachable --changed-paths 2>/dev/null || true

true
