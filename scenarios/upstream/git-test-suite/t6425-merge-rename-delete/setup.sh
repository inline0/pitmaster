#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo foo >A 2>/dev/null || true
git add A 2>/dev/null || true
git commit -m "initial" 2>/dev/null || true
git checkout -b rename 2>/dev/null || true
git mv A B 2>/dev/null || true
git commit -m "rename" 2>/dev/null || true
git checkout main 2>/dev/null || true
git rm A 2>/dev/null || true
git commit -m "delete" 2>/dev/null || true

true
