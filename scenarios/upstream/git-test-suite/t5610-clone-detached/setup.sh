#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo one >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo two >file 2>/dev/null || true
git commit -a -m two 2>/dev/null || true
git tag two 2>/dev/null || true
echo three >file 2>/dev/null || true
git commit -a -m three 2>/dev/null || true
git checkout main^0 2>/dev/null || true
echo three >expect 2>/dev/null || true

true
