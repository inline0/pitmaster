#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit a 2>/dev/null || true
test_commit b 2>/dev/null || true
test_commit c 2>/dev/null || true
git checkout b 2>/dev/null || true
test_commit d 2>/dev/null || true
test_commit e 2>/dev/null || true
git tag -l >tags 2>/dev/null || true
git branch branch-$tag $tag || return 1 2>/dev/null || true
git checkout c 2>/dev/null || true
test_commit g 2>/dev/null || true
git checkout d 2>/dev/null || true
test_commit i 2>/dev/null || true
git checkout b 2>/dev/null || true
test_commit f 2>/dev/null || true

true
