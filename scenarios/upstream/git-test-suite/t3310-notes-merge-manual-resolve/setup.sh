#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit 1st 2>/dev/null || true
test_commit 2nd 2>/dev/null || true
test_commit 3rd 2>/dev/null || true
test_commit 4th 2>/dev/null || true
test_commit 5th 2>/dev/null || true
git config core.notesRef refs/notes/x 2>/dev/null || true
git notes add -m "x notes on 2nd commit" 2nd 2>/dev/null || true
git notes add -m "x notes on 3rd commit" 3rd 2>/dev/null || true
git notes add -m "x notes on 4th commit" 4th 2>/dev/null || true
git update-ref refs/notes/y refs/notes/x 2>/dev/null || true
git config core.notesRef refs/notes/y 2>/dev/null || true
git notes add -f -m "y notes on 1st commit" 1st 2>/dev/null || true
git notes remove 2nd 2>/dev/null || true
git notes add -f -m "y notes on 3rd commit" 3rd 2>/dev/null || true
git notes add -f -m "y notes on 4th commit" 4th 2>/dev/null || true

true
