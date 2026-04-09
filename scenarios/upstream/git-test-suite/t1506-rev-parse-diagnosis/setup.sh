#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo one > file.txt 2>/dev/null || true
mkdir subdir 2>/dev/null || true
echo two > subdir/file.txt 2>/dev/null || true
echo three > subdir/file2.txt 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m init 2>/dev/null || true
echo four > index-only.txt 2>/dev/null || true
git add index-only.txt 2>/dev/null || true
echo five > disk-only.txt 2>/dev/null || true

true
