#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git add one two 2>/dev/null || true
test_commit base 2>/dev/null || true
mv one.tmp one 2>/dev/null || true
mv two.tmp two 2>/dev/null || true
git add -u 2>/dev/null || true
test_commit modified 2>/dev/null || true
cat one two >combined 2>/dev/null || true
git add combined 2>/dev/null || true
git rm one two 2>/dev/null || true
test_commit combined 2>/dev/null || true
cat >read-porcelain.pl <<-\EOF 2>/dev/null || true

true
