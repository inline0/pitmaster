#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit 1 2>/dev/null || true
git rm 1.t 2>/dev/null || true
echo hello >1.t 2>/dev/null || true
echo hello >file 2>/dev/null || true
echo hello >elif 2>/dev/null || true
git add -N file 2>/dev/null || true
git add elif 2>/dev/null || true
git add -N 1.t 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
