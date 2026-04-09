#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git commit --allow-empty -m "Initial empty commit" 2>/dev/null || true
test_commit first file a 2>/dev/null || true
test_commit second file 2>/dev/null || true
git checkout -b conflict-branch first 2>/dev/null || true
test_commit file-2 file-2 2>/dev/null || true
test_commit conflict file 2>/dev/null || true
test_commit third file 2>/dev/null || true
cat >expected-initial-signed <<-EOF 2>/dev/null || true
cat >expected-signed <<-EOF 2>/dev/null || true
cat >expected-signed-conflict <<-EOF 2>/dev/null || true
cat >expected-unsigned <<-EOF 2>/dev/null || true
git config alias.rbs "rebase --signoff" 2>/dev/null || true
git checkout --theirs file 2>/dev/null || true
git add file 2>/dev/null || true
git rebase --continue 2>/dev/null || true
git commit --amend -m "first" 2>/dev/null || true

true
