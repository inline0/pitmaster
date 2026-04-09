#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git checkout -b one 2>/dev/null || true
test_tick 2>/dev/null || true
git commit --allow-empty -m base 2>/dev/null || true
test_tick 2>/dev/null || true
git commit --allow-empty -m one 2>/dev/null || true
git checkout -b two HEAD^ 2>/dev/null || true
test_tick 2>/dev/null || true
git commit --allow-empty -m two 2>/dev/null || true
git merge origin/one 2>/dev/null || true

true
