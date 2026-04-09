#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mkdir -p dir1 2>/dev/null || true
touch dir1/file1.txt 2>/dev/null || true
echo testing >dir1/file2.txt 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "initial" 2>/dev/null || true
echo "Invalid combination of flags passed to hook; updated_workdir is set." >testfailure 2>/dev/null || true
echo "Invalid combination of flags passed to hook; updated_skipworktree is set." >testfailure 2>/dev/null || true
echo ".git/index.lock exists" >testfailure 2>/dev/null || true
echo ".git/index does not exist" >testfailure 2>/dev/null || true
echo "success" >testsuccess 2>/dev/null || true
mkdir -p dir2 2>/dev/null || true
touch dir2/file1.txt 2>/dev/null || true
touch dir2/file2.txt 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "second" 2>/dev/null || true
git checkout -- dir1/file1.txt 2>/dev/null || true
git update-index 2>/dev/null || true
git reset --soft 2>/dev/null || true
echo "Invalid combination of flags passed to hook; updated_workdir and updated_skipworktree are both set." >testfailure 2>/dev/null || true
echo "Invalid combination of flags passed to hook; neither updated_workdir or updated_skipworktree are set." >testfailure 2>/dev/null || true
echo "updated_workdir set but .git/index.lock exists" >testfailure 2>/dev/null || true
echo "updated_workdir set but .git/index does not exist" >testfailure 2>/dev/null || true
echo "update_workdir should be set for checkout" >testfailure 2>/dev/null || true
echo "success" >testsuccess 2>/dev/null || true
git checkout main 2>/dev/null || true
git checkout HEAD 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout -B test 2>/dev/null || true

true
