#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config --global advice.statusuoption false 2>/dev/null || true
mkdir broken 2>/dev/null || true
(
cd broken 2>/dev/null || true
git init 2>/dev/null || true
echo "[status] showuntrackedfiles = CORRUPT" >>.git/config 2>/dev/null || true
)
mkdir broken 2>/dev/null || true
(
cd broken 2>/dev/null || true
git init 2>/dev/null || true
echo "[status] showuntrackedfiles = CORRUPT" >>.git/config 2>/dev/null || true
)
git checkout -b upstream 2>/dev/null || true
test_commit upstream1 2>/dev/null || true
test_commit upstream2 2>/dev/null || true
git checkout --orphan main 2>/dev/null || true
mkdir dir1 2>/dev/null || true
mkdir dir2 2>/dev/null || true
git add . 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo 1 >dir1/modified 2>/dev/null || true
echo 2 >dir2/modified 2>/dev/null || true
echo 3 >dir2/added 2>/dev/null || true
git add dir2/added 2>/dev/null || true
git branch --set-upstream-to=upstream 2>/dev/null || true

true
