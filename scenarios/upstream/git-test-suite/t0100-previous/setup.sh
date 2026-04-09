#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit A 2>/dev/null || true
git checkout -b junk 2>/dev/null || true
git checkout - 2>/dev/null || true
echo refs/heads/main >expect 2>/dev/null || true
git symbolic-ref HEAD >actual 2>/dev/null || true
git branch -d @{-1} 2>/dev/null || true
git checkout -b junk2 2>/dev/null || true
git checkout - 2>/dev/null || true
echo refs/heads/main >expect 2>/dev/null || true
git symbolic-ref HEAD >actual 2>/dev/null || true
git checkout A 2>/dev/null || true
test_commit B 2>/dev/null || true
git checkout A 2>/dev/null || true
test_commit C 2>/dev/null || true
test_commit D 2>/dev/null || true
git branch -f main B 2>/dev/null || true
git branch -f other 2>/dev/null || true
git checkout other 2>/dev/null || true
git checkout main 2>/dev/null || true
git merge @{-1} 2>/dev/null || true

true
