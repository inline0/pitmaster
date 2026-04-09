#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit one 2>/dev/null || true
test_commit two 2>/dev/null || true
test_commit three 2>/dev/null || true
echo "add broken entry" >msg 2>/dev/null || true
test_tick 2>/dev/null || true
git update-ref HEAD "$commit" 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -a -m "back to normal" 2>/dev/null || true

true
