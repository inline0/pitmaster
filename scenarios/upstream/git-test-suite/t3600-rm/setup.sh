#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

touch -- foo bar baz "space embedded" -q 2>/dev/null || true
git add -- foo bar baz "space embedded" -q 2>/dev/null || true
git commit -m "add normal files" 2>/dev/null || true
touch -- "tab	embedded" "newline${LF}embedded" 2>/dev/null || true
git add -- "tab	embedded" "newline${LF}embedded" 2>/dev/null || true
git commit -m "add files with tabs and newlines" 2>/dev/null || true

true
