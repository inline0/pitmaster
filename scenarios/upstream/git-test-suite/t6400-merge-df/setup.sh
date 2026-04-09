#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo Hello >init 2>/dev/null || true
git add init 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git branch B 2>/dev/null || true
mkdir dir 2>/dev/null || true
echo foo >dir/foo 2>/dev/null || true
git add dir/foo 2>/dev/null || true
git commit -m "File: dir/foo" 2>/dev/null || true
git checkout B 2>/dev/null || true
echo file dir >dir 2>/dev/null || true
git add dir 2>/dev/null || true
git commit -m "File: dir" 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout main 2>/dev/null || true
mkdir before 2>/dev/null || true
echo FILE >before/one 2>/dev/null || true
echo FILE >after 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m first 2>/dev/null || true
git mv before after 2>/dev/null || true
git commit -m move 2>/dev/null || true
git checkout -b para HEAD^ 2>/dev/null || true
echo COMPLETELY ANOTHER FILE >another 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m para 2>/dev/null || true
git merge main 2>/dev/null || true

true
