#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit E file1 2>/dev/null || true
test_commit D file1 2>/dev/null || true
test_commit C file1 2>/dev/null || true
git reset --hard C 2>/dev/null || true
git branch branch1 2>/dev/null || true
git branch branch2 2>/dev/null || true
git checkout branch1 2>/dev/null || true
test_commit B1 file1 2>/dev/null || true
git checkout branch2 2>/dev/null || true
test_commit B file1 2>/dev/null || true

true
