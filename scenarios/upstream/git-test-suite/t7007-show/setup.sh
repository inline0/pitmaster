#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit main1 2>/dev/null || true
test_commit main2 2>/dev/null || true
test_commit main3 2>/dev/null || true
git tag -m "annotated tag" annotated 2>/dev/null || true
git checkout -b side HEAD^^ 2>/dev/null || true
test_commit side2 2>/dev/null || true
test_commit side3 2>/dev/null || true
test_merge merge main3 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
