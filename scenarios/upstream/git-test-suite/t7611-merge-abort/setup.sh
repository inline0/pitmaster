#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo foo > foo 2>/dev/null || true
echo bar > bar 2>/dev/null || true
echo baz > baz 2>/dev/null || true
git add foo bar baz 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo bazz > baz 2>/dev/null || true
git commit -a -m "second" 2>/dev/null || true
git checkout -b conflict_branch HEAD^ 2>/dev/null || true
echo barf > bar 2>/dev/null || true
echo bazf > baz 2>/dev/null || true
git commit -a -m "conflict" 2>/dev/null || true
git checkout -b clean_branch HEAD^ 2>/dev/null || true
echo bart > bar 2>/dev/null || true
git commit -a -m "clean" 2>/dev/null || true
git checkout main 2>/dev/null || true

true
