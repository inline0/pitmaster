#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git add file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git branch other 2>/dev/null || true
echo upstream >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m upstream 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout other 2>/dev/null || true
echo dirt >file 2>/dev/null || true
git update-index --assume-unchanged file 2>/dev/null || true

true
