#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit unrelated bar 2>/dev/null || true
test_commit vanilla foo 2>/dev/null || true
git config core.protectNTFS false 2>/dev/null || true
git update-index --add --cacheinfo 100644 "$(git rev-parse HEAD:foo)" "f*" 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m star 2>/dev/null || true
test_commit bracket "f[o][o]" 2>/dev/null || true
echo vanilla >expect 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true

true
