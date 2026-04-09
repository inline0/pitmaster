#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mkdir -p sub1/sub2 2>/dev/null || true
echo top >top 2>/dev/null || true
echo file1 >sub1/file1 2>/dev/null || true
echo file2 >sub1/sub2/file2 2>/dev/null || true
git add top sub1/file1 sub1/sub2/file2 2>/dev/null || true
git commit -m commit 2>/dev/null || true
cat <<-\EOF >expected 2>/dev/null || true
cat <<-\EOF >expected 2>/dev/null || true

true
