#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo zero > zero 2>/dev/null || true
git add zero 2>/dev/null || true
git commit -m zero 2>/dev/null || true
echo one > one 2>/dev/null || true
echo two > two 2>/dev/null || true
git add one two 2>/dev/null || true
git commit -m onetwo 2>/dev/null || true
git update-index --assume-unchanged one 2>/dev/null || true
echo borked >> one 2>/dev/null || true

true
