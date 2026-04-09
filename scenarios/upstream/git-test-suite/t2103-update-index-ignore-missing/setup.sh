#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git reset --hard initial 2>/dev/null || true
echo 2 >one 2>/dev/null || true
echo 1 >one 2>/dev/null || true
git update-index --refresh 2>/dev/null || true
git update-index --ignore-missing --refresh 2>/dev/null || true
git reset --hard initial 2>/dev/null || true
git rm --cached one 2>/dev/null || true
git update-index --unmerged --refresh 2>/dev/null || true
echo 2 >two 2>/dev/null || true
git reset --hard initial 2>/dev/null || true
git reset --hard initial 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "sub second" --allow-empty 2>/dev/null || true
git update-index --ignore-submodules --refresh 2>/dev/null || true

true
