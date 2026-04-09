#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git checkout --orphan newroot 2>/dev/null || true
test_commit five 2>/dev/null || true
git checkout -b sidebranch two 2>/dev/null || true
test_commit six 2>/dev/null || true
git checkout -b anotherbranch three 2>/dev/null || true
test_commit seven 2>/dev/null || true
git checkout -b yetanotherbranch four 2>/dev/null || true
test_commit eight 2>/dev/null || true
git checkout main 2>/dev/null || true
test_tick 2>/dev/null || true
git merge --allow-unrelated-histories -m normalmerge newroot 2>/dev/null || true
git tag normalmerge 2>/dev/null || true
test_tick 2>/dev/null || true
git merge -m tripus sidebranch anotherbranch 2>/dev/null || true
git tag tripus 2>/dev/null || true
git checkout -b tetrabranch normalmerge 2>/dev/null || true
test_tick 2>/dev/null || true
git merge -m tetrapus sidebranch anotherbranch yetanotherbranch 2>/dev/null || true
git tag tetrapus 2>/dev/null || true
git checkout main 2>/dev/null || true

true
