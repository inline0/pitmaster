#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo one >foo && git add foo && git commit -m one 2>/dev/null || true
echo one >foo && git add foo && git commit -m one 2>/dev/null || true
echo two >foo && git add foo && git commit -m two 2>/dev/null || true
echo one >foo && git add foo && git commit -m one 2>/dev/null || true
echo two >foo && git add foo && git commit -m two 2>/dev/null || true
git reset --hard HEAD^ 2>/dev/null || true

true
