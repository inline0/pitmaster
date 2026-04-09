#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit one 2>/dev/null || true
test_commit two 2>/dev/null || true
test_commit fork-point 2>/dev/null || true
git switch -c side 2>/dev/null || true
test_commit three 2>/dev/null || true
git switch main 2>/dev/null || true
git merge --no-ff side 2>/dev/null || true
git tag merged 2>/dev/null || true
git reset --hard merged 2>/dev/null || true
git rebase -fr --gpg-sign -s resolve --root 2>/dev/null || true

true
