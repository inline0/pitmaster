#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_create_repo dir-rename 2>/dev/null || true
mkdir x 2>/dev/null || true
test_write_lines a b c d e f g h i >l 2>/dev/null || true
git add x l 2>/dev/null || true
git commit -m "Initial" 2>/dev/null || true
git branch O 2>/dev/null || true
git branch A 2>/dev/null || true
git branch B 2>/dev/null || true
git checkout A 2>/dev/null || true
git mv x y 2>/dev/null || true
git mv l letters 2>/dev/null || true
git commit -m "Rename x to y, l to letters" 2>/dev/null || true
git checkout B 2>/dev/null || true
echo j >>l 2>/dev/null || true
git add l x/d 2>/dev/null || true
git commit -m "Modify l, add x/d" 2>/dev/null || true
git checkout B^0 2>/dev/null || true
git checkout B^0 2>/dev/null || true

true
