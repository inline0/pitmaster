#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit root 2>/dev/null || true
test_commit a a a 2>/dev/null || true
test_commit b b b 2>/dev/null || true
git checkout -b rebase-me root 2>/dev/null || true
test_commit rebase-a a aa 2>/dev/null || true
test_commit rebase-b b bb 2>/dev/null || true
test_commit rebase-$i c $i || return 1 2>/dev/null || true
git checkout main 2>/dev/null || true
cat >rebase-todo <<-EOF 2>/dev/null || true
echo "foo" > file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m "first" 2>/dev/null || true

true
