#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo hello >world 2>/dev/null || true
echo hello >all 2>/dev/null || true
git add all world 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git branch world 2>/dev/null || true
git checkout world -- 2>/dev/null || true

true
