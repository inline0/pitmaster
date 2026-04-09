#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo line1 >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m commit1 2>/dev/null || true
echo more >>file 2>/dev/null || true
echo e | env GIT_EDITOR=": >editor_was_started" git commit -p -m commit2 file 2>/dev/null || true
echo more >>file 2>/dev/null || true
echo e | env GIT_EDITOR=": >editor_was_started" git commit -p -m commit3 file 2>/dev/null || true

true
