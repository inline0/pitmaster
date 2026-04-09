#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git commit --allow-empty -m empty 2>/dev/null || true
echo content >file1 2>/dev/null || true
mkdir subdir 2>/dev/null || true
echo other content >subdir/file2 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo changed >file1 2>/dev/null || true
echo changed >subdir/file2 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
test_commit zero file0 2>/dev/null || true
test_commit base subdir/file0 2>/dev/null || true
git switch -c br1 2>/dev/null || true
test_commit one file0 2>/dev/null || true
test_commit sub1 subdir/file0 2>/dev/null || true
git switch -c br2 base 2>/dev/null || true
test_commit two file0 2>/dev/null || true
git switch -c br3 2>/dev/null || true
test_commit sub3 subdir/file0 2>/dev/null || true

true
