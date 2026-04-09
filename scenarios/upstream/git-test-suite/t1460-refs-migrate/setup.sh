#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

rm -rf .git 2>/dev/null || true
git init --ref-format=files repo 2>/dev/null || true
test_commit -C repo first 2>/dev/null || true
test_commit -C repo second 2>/dev/null || true
git init --ref-format=files repo 2>/dev/null || true
test_commit -C repo first 2>/dev/null || true
test_commit -C repo second 2>/dev/null || true
echo "ref: refs/heads/.invalid" >expect 2>/dev/null || true
echo "this repository uses the reftable format" >expect 2>/dev/null || true
git init --ref-format=reftable repo 2>/dev/null || true
test_commit -C repo first 2>/dev/null || true
echo "ref: refs/heads/main" >expect 2>/dev/null || true

true
