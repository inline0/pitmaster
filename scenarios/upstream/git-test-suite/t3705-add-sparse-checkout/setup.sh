#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

rm sparse_entry 2>/dev/null || true
rm sparse_entry 2>/dev/null || true
git add -A 2>stderr 2>/dev/null || true
rm sparse_entry 2>/dev/null || true
cat sparse_error_header >expect 2>/dev/null || true
echo . >>expect 2>/dev/null || true
cat sparse_hint >>expect 2>/dev/null || true
echo "sparse_entry text=auto" >.gitattributes 2>/dev/null || true
rm sparse_entry 2>/dev/null || true
echo modified >sparse_entry 2>/dev/null || true
git add "*_entry" 2>stderr 2>/dev/null || true
test_commit a 2>/dev/null || true
echo >>sparse_entry 2>/dev/null || true
git update-index --no-skip-worktree sparse_entry 2>/dev/null || true
git add --sparse --chmod=+x sparse_entry 2>stderr 2>/dev/null || true
git reset 2>/dev/null || true
git add --sparse --renormalize sparse_entry 2>/dev/null || true

true
