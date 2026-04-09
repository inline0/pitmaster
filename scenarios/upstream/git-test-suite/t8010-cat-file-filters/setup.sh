#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo "*.txt eol=crlf diff=txt" >.gitattributes 2>/dev/null || true
echo "hello" | append_cr >world.txt 2>/dev/null || true
git add .gitattributes world.txt 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Initial commit" 2>/dev/null || true

true
