#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_create_repo df_plus_modify_delete 2>/dev/null || true
test_write_lines a b c d e f g h >letters 2>/dev/null || true
git add letters 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git checkout -b modify 2>/dev/null || true
echo i >>letters 2>/dev/null || true
echo "version 2" >letters.txt 2>/dev/null || true
git add letters letters.txt 2>/dev/null || true
git commit -m modified 2>/dev/null || true
git checkout -b delete HEAD^ 2>/dev/null || true
git rm letters 2>/dev/null || true
mkdir letters 2>/dev/null || true
echo "version 1" >letters.txt 2>/dev/null || true
git add letters letters.txt 2>/dev/null || true
git commit -m deleted 2>/dev/null || true
git checkout delete^0 2>/dev/null || true
git read-tree --reset HEAD 2>/dev/null || true
git checkout delete^0 2>/dev/null || true
git reset --hard 2>/dev/null || true

true
