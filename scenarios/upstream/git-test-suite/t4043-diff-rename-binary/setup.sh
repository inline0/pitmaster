#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init 2>/dev/null || true
echo foo > foo 2>/dev/null || true
echo "barQ" | q_to_nul > bar 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "Initial commit" 2>/dev/null || true
mkdir sub 2>/dev/null || true
git mv bar foo sub/ 2>/dev/null || true
git commit -m "Moved to sub/" 2>/dev/null || true

true
