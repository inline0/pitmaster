#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit 1 2>/dev/null || true
git checkout -b side 2>/dev/null || true
test_commit 2 2>/dev/null || true
git checkout main 2>/dev/null || true
test_commit 3 2>/dev/null || true
test_commit 4 2>/dev/null || true
git branch --track my-side origin/side 2>/dev/null || true
git branch --track local-main main 2>/dev/null || true
git branch --track fun@ny origin/side 2>/dev/null || true
git branch --track @funny origin/side 2>/dev/null || true
git branch --track funny@ origin/side 2>/dev/null || true
git branch bad-upstream 2>/dev/null || true
git config branch.bad-upstream.remote main-only 2>/dev/null || true
git config branch.bad-upstream.merge refs/heads/side 2>/dev/null || true
echo refs/remotes/origin/main >expect 2>/dev/null || true
echo refs/remotes/origin/main >expect 2>/dev/null || true

true
