#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

rm -rf .git 2>/dev/null || true
git init to-clone 2>/dev/null || true
(
cd to-clone 2>/dev/null || true
git branch -m base 2>/dev/null || true
test_commit first 2>/dev/null || true
test_commit second 2>/dev/null || true
test_commit third 2>/dev/null || true
git switch -c parallel first 2>/dev/null || true
mkdir -p 1/2 2>/dev/null || true
test_commit 1/2/3 2>/dev/null || true
git switch base 2>/dev/null || true
git config uploadpack.allowfilter true 2>/dev/null || true
git config uploadpack.allowanysha1inwant true 2>/dev/null || true
)
(
cd $enlistment/src 2>/dev/null || true
)
(
cd $enlistment/src 2>/dev/null || true
)

true
