#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m empty 2>/dev/null || true
echo content >file1
mkdir subdir
echo other content >subdir/file2
git add . 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo '1	0	$expect' >expected
echo changed >file1
echo changed >subdir/file2
