#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git checkout rename2 2>/dev/null || true
git cherry-pick added 2>/dev/null || true
git checkout rename1 2>/dev/null || true
git revert added 2>/dev/null || true
(
cd copy 2>/dev/null || true
git checkout initial 2>/dev/null || true
git cherry-pick added 2>/dev/null || true
)
echo content >extra_file 2>/dev/null || true
git add extra_file 2>/dev/null || true
git switch --orphan unborn 2>/dev/null || true
git rm --cached -r . 2>/dev/null || true
git cherry-pick initial 2>/dev/null || true
git checkout --detach 2>/dev/null || true
git branch -D unborn 2>/dev/null || true
git switch --orphan unborn 2>/dev/null || true
git cherry-pick initial --allow-empty 2>/dev/null || true
git checkout unborn 2>/dev/null || true
test_commit to-pick actual content 2>/dev/null || true
git checkout main 2>/dev/null || true
git cherry-pick - 2>/dev/null || true
echo content >expect 2>/dev/null || true

true
