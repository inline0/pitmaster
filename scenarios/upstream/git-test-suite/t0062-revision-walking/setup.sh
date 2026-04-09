#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo a > a 2>/dev/null || true
git add a 2>/dev/null || true
git commit -m "add a" 2>/dev/null || true
echo b > b 2>/dev/null || true
git add b 2>/dev/null || true
git commit -m "add b" 2>/dev/null || true

true
