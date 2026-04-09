#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit --date "$FUTURE_DATE" future-1 2>/dev/null || true
test_commit --date "$UNIX_EPOCH_ZERO" old-1 2>/dev/null || true
git commit-graph write --reachable 2>/dev/null || true
test_commit --date "$FUTURE_DATE" future-2 2>/dev/null || true
test_commit --date "$UNIX_EPOCH_ZERO" old-2 2>/dev/null || true
git commit-graph write --reachable --split=no-merge 2>/dev/null || true
test_commit extra 2>/dev/null || true
git commit-graph write --reachable --split=no-merge 2>/dev/null || true
git commit-graph write --reachable 2>/dev/null || true
mv .git/objects/info/commit-graph commit-graph-upgraded 2>/dev/null || true
git commit-graph write --reachable 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit --date "$UNIX_EPOCH_ZERO" 1 2>/dev/null || true
test_commit 2 2>/dev/null || true
test_commit --date "$UNIX_EPOCH_ZERO" 3 2>/dev/null || true
git commit-graph write --reachable 2>/dev/null || true
test_commit --date "$FUTURE_DATE" 4 2>/dev/null || true
test_commit 5 2>/dev/null || true
test_commit --date "$UNIX_EPOCH_ZERO" 6 2>/dev/null || true
git branch left 2>/dev/null || true
git reset --hard 3 2>/dev/null || true
test_commit 7 2>/dev/null || true
test_commit --date "$FUTURE_DATE" 8 2>/dev/null || true
test_commit 9 2>/dev/null || true
git branch right 2>/dev/null || true
git reset --hard 3 2>/dev/null || true
test_merge M left right 2>/dev/null || true
git commit-graph write --reachable 2>/dev/null || true
git commit-graph verify 2>/dev/null || true
git init repo-uint32-max 2>/dev/null || true
test_commit -C repo-uint32-max --date "@4294967297 +0000" 1 2>/dev/null || true

true
