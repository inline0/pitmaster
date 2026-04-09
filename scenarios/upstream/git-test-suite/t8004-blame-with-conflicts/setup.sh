#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo "Old line" > file1 2>/dev/null || true
git add file1 2>/dev/null || true
git commit --author "Old Line <ol@localhost>" -m file1.a 2>/dev/null || true
git checkout -b foo 2>/dev/null || true
git rm file1 2>/dev/null || true
echo "New line ..."  > file2 2>/dev/null || true
echo "... and more" >> file2 2>/dev/null || true
git add file2 2>/dev/null || true
git commit --author "U Gly <ug@localhost>" -m ugly 2>/dev/null || true
git checkout main 2>/dev/null || true
git commit --author "Old Line <ol@localhost>" -a -m file1.b 2>/dev/null || true
git checkout foo 2>/dev/null || true
git rm file1 2>/dev/null || true
git commit --author "M Result <mr@localhost>" -a -m merged 2>/dev/null || true
git checkout main 2>/dev/null || true
mv X file1 2>/dev/null || true
git commit --author "No Bla <nb@localhost>" -a -m replace 2>/dev/null || true
git checkout foo 2>/dev/null || true

true
