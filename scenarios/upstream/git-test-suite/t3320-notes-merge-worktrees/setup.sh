#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit tantrum 2>/dev/null || true
git config core.notesRef refs/notes/x 2>/dev/null || true
git notes add -m "x notes on tantrum" tantrum 2>/dev/null || true
git update-ref refs/notes/y refs/notes/x 2>/dev/null || true
git config core.notesRef refs/notes/y 2>/dev/null || true
git notes remove tantrum 2>/dev/null || true

true
