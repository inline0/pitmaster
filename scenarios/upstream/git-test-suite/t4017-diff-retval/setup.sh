#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo "1 " >a 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m zeroth 2>/dev/null || true
echo 1 >a 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m first 2>/dev/null || true
echo 2 >b 2>/dev/null || true
git add . 2>/dev/null || true
git commit -a -m second 2>/dev/null || true

true
