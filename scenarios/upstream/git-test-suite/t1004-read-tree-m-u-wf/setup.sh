#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir subdir 2>/dev/null || true
echo >file1 file one 2>/dev/null || true
echo >file2 file two 2>/dev/null || true
echo >subdir/file1 file one in subdirectory 2>/dev/null || true
echo >subdir/file2 file two in subdirectory 2>/dev/null || true
git update-index --add file1 file2 subdir/file1 subdir/file2 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git branch side 2>/dev/null || true
git tag -f branch-point 2>/dev/null || true
git update-index --remove file2 subdir/file2 2>/dev/null || true
git commit -a -m "main removes file2 and subdir/file2" 2>/dev/null || true
echo >file2 main creates untracked file2 2>/dev/null || true
echo >subdir/file2 main creates untracked subdir/file2 2>/dev/null || true

true
