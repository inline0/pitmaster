#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git add foo 2>/dev/null || true
git commit -m orig 2>/dev/null || true
git branch A 2>/dev/null || true
git branch B 2>/dev/null || true
git checkout A 2>/dev/null || true
git add foo 2>/dev/null || true
git commit -m A 2>/dev/null || true
git checkout B 2>/dev/null || true
echo "q qfoo();" | q_to_tab >>foo 2>/dev/null || true
git add foo 2>/dev/null || true
git commit -m B 2>/dev/null || true

true
