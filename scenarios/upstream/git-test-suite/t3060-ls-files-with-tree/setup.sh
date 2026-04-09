#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo file >expected 2>/dev/null || true
mkdir sub 2>/dev/null || true
echo file-$num >>expected || 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "add a bunch of files" 2>/dev/null || true
git commit -a -m "remove them all" 2>/dev/null || true
mkdir a_directory_that_sorts_before_sub 2>/dev/null || true
mkdir sub 2>/dev/null || true
git add . 2>/dev/null || true

true
