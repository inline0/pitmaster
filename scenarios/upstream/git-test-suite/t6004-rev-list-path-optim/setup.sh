#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git checkout -b side 2>/dev/null || true
echo Irrelevant >c 2>/dev/null || true
echo Irrelevant >d/f 2>/dev/null || true
git add c d/f 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Side makes an irrelevant commit" 2>/dev/null || true
git tag side_c0 2>/dev/null || true
echo "More Irrelevancy" >c 2>/dev/null || true
git add c 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Side makes another irrelevant commit" 2>/dev/null || true
echo Bye >a 2>/dev/null || true
git add a 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Side touches a" 2>/dev/null || true
git tag side_a1 2>/dev/null || true
echo "Yet more Irrelevancy" >c 2>/dev/null || true
git add c 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Side makes yet another irrelevant commit" 2>/dev/null || true
git checkout main 2>/dev/null || true
echo Another >b 2>/dev/null || true
echo Munged >d/z 2>/dev/null || true
git add b d/z 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Main touches b" 2>/dev/null || true
git tag main_b0 2>/dev/null || true
git merge side 2>/dev/null || true
echo Touched >b 2>/dev/null || true
git add b 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Main touches b again" 2>/dev/null || true

true
