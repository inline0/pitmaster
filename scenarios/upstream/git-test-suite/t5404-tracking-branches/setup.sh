#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo 1 >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m 1 2>/dev/null || true
git branch b1 2>/dev/null || true
git branch b2 2>/dev/null || true
git branch b3 2>/dev/null || true
git checkout b1 2>/dev/null || true
echo b1 >>file 2>/dev/null || true
git commit -a -m b1 2>/dev/null || true
git checkout b2 2>/dev/null || true
echo b2 >>file 2>/dev/null || true
git commit -a -m b2 2>/dev/null || true
git checkout -b b1 origin/b1 2>/dev/null || true
echo aa-b1 >>file 2>/dev/null || true
git commit -a -m aa-b1 2>/dev/null || true
git checkout -b b2 origin/b2 2>/dev/null || true
echo aa-b2 >>file 2>/dev/null || true
git commit -a -m aa-b2 2>/dev/null || true
git checkout main 2>/dev/null || true
echo aa-main >>file 2>/dev/null || true
git commit -a -m aa-main 2>/dev/null || true

true
