#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo main >file1 2>/dev/null || true
echo main spaced >"spaced name" 2>/dev/null || true
echo main file11 >file11 2>/dev/null || true
echo main file12 >file12 2>/dev/null || true
echo main file13 >file13 2>/dev/null || true
echo main file14 >file14 2>/dev/null || true
mkdir subdir 2>/dev/null || true
echo main sub >subdir/file3 2>/dev/null || true
test_create_repo submod 2>/dev/null || true
git add foo 2>/dev/null || true
git commit -m "Add foo" 2>/dev/null || true
git submodule add file:///dev/null submod 2>/dev/null || true
git add file1 "spaced name" file1[1-4] subdir/file3 .gitmodules submod 2>/dev/null || true
git commit -m "add initial versions" 2>/dev/null || true
git checkout -b branch1 main 2>/dev/null || true
git submodule update -N 2>/dev/null || true
echo branch1 change >file1 2>/dev/null || true
echo branch1 newfile >file2 2>/dev/null || true
echo branch1 spaced >"spaced name" 2>/dev/null || true
echo branch1 both added >both 2>/dev/null || true
echo branch1 change file11 >file11 2>/dev/null || true
echo branch1 change file13 >file13 2>/dev/null || true
echo branch1 sub >subdir/file3 2>/dev/null || true
echo branch1 submodule >bar 2>/dev/null || true
git add bar 2>/dev/null || true
git commit -m "Add bar on branch1" 2>/dev/null || true
git checkout -b submod-branch1 2>/dev/null || true
git add file1 "spaced name" file11 file13 file2 subdir/file3 submod 2>/dev/null || true
git add both 2>/dev/null || true
git rm file12 2>/dev/null || true
git commit -m "branch1 changes" 2>/dev/null || true
git checkout -b delete-base branch1 2>/dev/null || true
mkdir -p a/a 2>/dev/null || true
test_write_lines one two 3 4 >a/a/file.txt 2>/dev/null || true
git add a/a/file.txt 2>/dev/null || true
git commit -m"base file" 2>/dev/null || true
git checkout -b move-to-b delete-base 2>/dev/null || true
mkdir -p b/b 2>/dev/null || true
git mv a/a/file.txt b/b/file.txt 2>/dev/null || true
test_write_lines one two 4 >b/b/file.txt 2>/dev/null || true
git commit -a -m"move to b" 2>/dev/null || true
git checkout -b move-to-c delete-base 2>/dev/null || true
mkdir -p c/c 2>/dev/null || true
git mv a/a/file.txt c/c/file.txt 2>/dev/null || true
test_write_lines one two 3 >c/c/file.txt 2>/dev/null || true
git commit -a -m"move to c" 2>/dev/null || true
git checkout -b stash1 main 2>/dev/null || true
echo stash1 change file11 >file11 2>/dev/null || true
git add file11 2>/dev/null || true
git commit -m "stash1 changes" 2>/dev/null || true
git checkout -b stash2 main 2>/dev/null || true
echo stash2 change file11 >file11 2>/dev/null || true
git add file11 2>/dev/null || true
git commit -m "stash2 changes" 2>/dev/null || true
git checkout main 2>/dev/null || true
git submodule update -N 2>/dev/null || true
echo main updated >file1 2>/dev/null || true
echo main new >file2 2>/dev/null || true
echo main updated spaced >"spaced name" 2>/dev/null || true
echo main both added >both 2>/dev/null || true
echo main updated file12 >file12 2>/dev/null || true
echo main updated file14 >file14 2>/dev/null || true
echo main new sub >subdir/file3 2>/dev/null || true
echo main submodule >bar 2>/dev/null || true
git add bar 2>/dev/null || true
git commit -m "Add bar on main" 2>/dev/null || true
git checkout -b submod-main 2>/dev/null || true
git add file1 "spaced name" file12 file14 file2 subdir/file3 submod 2>/dev/null || true
git add both 2>/dev/null || true
git rm file11 2>/dev/null || true
git commit -m "main updates" 2>/dev/null || true
git checkout -b order-file-start main 2>/dev/null || true
echo start >a 2>/dev/null || true
echo start >b 2>/dev/null || true
git add a b 2>/dev/null || true
git commit -m start 2>/dev/null || true
git checkout -b order-file-side1 order-file-start 2>/dev/null || true
echo side1 >a 2>/dev/null || true
echo side1 >b 2>/dev/null || true
git add a b 2>/dev/null || true

true
