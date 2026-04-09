#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit init 2>/dev/null || true
echo modified >>init.t 2>/dev/null || true
cat >expected <<-EOF 2>/dev/null || true
cat >expected.swt <<-\EOF 2>/dev/null || true
mkdir sub subsub 2>/dev/null || true
touch sub/added sub/addedtoo subsub/added 2>/dev/null || true
git add init.t sub/added sub/addedtoo subsub/added 2>/dev/null || true
git commit -m "modified and added" 2>/dev/null || true
git tag top 2>/dev/null || true
git rm sub/added 2>/dev/null || true
git commit -m removed 2>/dev/null || true
git tag removed 2>/dev/null || true
git checkout top 2>/dev/null || true
mkdir .git/info 2>/dev/null || true
echo >.git/info/sparse-checkout 2>/dev/null || true

true
