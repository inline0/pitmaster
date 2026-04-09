#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo "root" >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m "zeroth" 2>/dev/null || true
git checkout -b side 2>/dev/null || true
echo "foo" >foo 2>/dev/null || true
git add foo 2>/dev/null || true
git commit -m "make it non-ff" 2>/dev/null || true
git branch side-orig side 2>/dev/null || true
git checkout main 2>/dev/null || true
git checkout -b conflicting-a main 2>/dev/null || true
echo a >conflicting 2>/dev/null || true
git add conflicting 2>/dev/null || true
git commit -m conflicting-a 2>/dev/null || true
git checkout -b conflicting-b main 2>/dev/null || true
echo b >conflicting 2>/dev/null || true
git add conflicting 2>/dev/null || true
git commit -m conflicting-b 2>/dev/null || true
echo "foo" >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m "first" 2>/dev/null || true

true
