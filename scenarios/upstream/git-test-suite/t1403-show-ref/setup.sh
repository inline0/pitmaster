#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo $(git rev-parse refs/tags/A) refs/tags/A >expect 2>/dev/null || true
echo $(git rev-parse refs/tags/A) refs/tags/A >expect 2>/dev/null || true
echo $(git rev-parse refs/heads/main) refs/heads/main >expect 2>/dev/null || true
cat expect.branches expect.tags >expect 2>/dev/null || true
echo $(git rev-parse HEAD) HEAD >expect 2>/dev/null || true
git update-ref CHERRY_PICK_HEAD HEAD $ZERO_OID 2>/dev/null || true
rm -f "$file" 2>/dev/null || true
(
git init dangling 2>/dev/null || true
cd dangling 2>/dev/null || true
test_commit dangling 2>/dev/null || true
)

true
