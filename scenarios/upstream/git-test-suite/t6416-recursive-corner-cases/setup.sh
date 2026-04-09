#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init basic-rename 2>/dev/null || true
printf "line %d in a sample file\n" $ten >one 2>/dev/null || true
printf "line %d in another sample file\n" $ten >two 2>/dev/null || true
git add one two 2>/dev/null || true
test_tick && git commit -m initial 2>/dev/null || true
git branch L1 2>/dev/null || true
git checkout -b R1 2>/dev/null || true
git mv one three 2>/dev/null || true
test_tick && git commit -m R1 2>/dev/null || true
git checkout L1 2>/dev/null || true
git mv two three 2>/dev/null || true
test_tick && git commit -m L1 2>/dev/null || true
git checkout L1^0 2>/dev/null || true
test_tick && git merge -s ours R1 2>/dev/null || true
git tag L2 2>/dev/null || true
git checkout R1^0 2>/dev/null || true
test_tick && git merge -s ours L1 2>/dev/null || true
git tag R2 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout L2^0 2>/dev/null || true
git init rename-modify 2>/dev/null || true
printf "line %d in a sample file\n" $ten >one 2>/dev/null || true
printf "line %d in another sample file\n" $ten >two 2>/dev/null || true
git add one two 2>/dev/null || true
test_tick && git commit -m initial 2>/dev/null || true
git branch L1 2>/dev/null || true
git checkout -b R1 2>/dev/null || true
git mv one three 2>/dev/null || true
echo more >>two 2>/dev/null || true
git add two 2>/dev/null || true
test_tick && git commit -m R1 2>/dev/null || true
git checkout L1 2>/dev/null || true
git mv two three 2>/dev/null || true
test_tick && git commit -m L1 2>/dev/null || true
git checkout L1^0 2>/dev/null || true
test_tick && git merge -s ours R1 2>/dev/null || true
git tag L2 2>/dev/null || true
git checkout R1^0 2>/dev/null || true
test_tick && git merge -s ours L1 2>/dev/null || true
git tag R2 2>/dev/null || true

true
