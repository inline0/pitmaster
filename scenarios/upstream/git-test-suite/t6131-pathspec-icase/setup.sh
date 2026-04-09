#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit bar bar 2>/dev/null || true
test_commit bAr bAr 2>/dev/null || true
test_commit BAR BAR 2>/dev/null || true
mkdir foo 2>/dev/null || true
test_commit foo/bar foo/bar 2>/dev/null || true
test_commit foo/bAr foo/bAr 2>/dev/null || true
test_commit foo/BAR foo/BAR 2>/dev/null || true
mkdir fOo 2>/dev/null || true
test_commit fOo/bar fOo/bar 2>/dev/null || true
test_commit fOo/bAr fOo/bAr 2>/dev/null || true
test_commit fOo/BAR fOo/BAR 2>/dev/null || true
mkdir FOO 2>/dev/null || true
test_commit FOO/bar FOO/bar 2>/dev/null || true
test_commit FOO/bAr FOO/bAr 2>/dev/null || true
test_commit FOO/BAR FOO/BAR 2>/dev/null || true
echo bar >expect 2>/dev/null || true
cat <<-EOF >expect 2>/dev/null || true

true
