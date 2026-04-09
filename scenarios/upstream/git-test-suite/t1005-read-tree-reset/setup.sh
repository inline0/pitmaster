#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init 2>/dev/null || true
mkdir df 2>/dev/null || true
echo content >df/file 2>/dev/null || true
git add df/file 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo content >df 2>/dev/null || true
git add df 2>/dev/null || true
echo content >new 2>/dev/null || true
git add new 2>/dev/null || true
git commit -m two 2>/dev/null || true

true
