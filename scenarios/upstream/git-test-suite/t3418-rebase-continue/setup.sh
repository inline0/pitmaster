#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit "commit-new-file-F1" F1 1 2>/dev/null || true
test_commit "commit-new-file-F2" F2 2 2>/dev/null || true
git checkout -b topic HEAD^ 2>/dev/null || true
test_commit "commit-new-file-F2-on-topic-branch" F2 22 2>/dev/null || true
git checkout main 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout main 2>/dev/null || true
git rebase --continue 2>/dev/null || true
git checkout -f --detach topic 2>/dev/null || true
git read-tree --reset -u HEAD 2>/dev/null || true
git rebase --continue 2>/dev/null || true

true
