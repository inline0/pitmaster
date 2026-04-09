#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git checkout main 2>/dev/null || true
git checkout main 2>/dev/null || true
git cherry-pick empty-message-branch 2>/dev/null || true
git checkout -f main 2>/dev/null || true
git cherry-pick --allow-empty-message empty-message-branch 2>/dev/null || true
git checkout main 2>/dev/null || true
echo fourth >>file2 2>/dev/null || true
git add file2 2>/dev/null || true
git commit -m "fourth" 2>/dev/null || true

true
