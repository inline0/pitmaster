#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit initial 2>/dev/null || true
git checkout main -b $i || return $? 2>/dev/null || true
test_commit $i $i $i tag$i || return $? 2>/dev/null || true
git checkout 1 -b merge 2>/dev/null || true
test_merge octopus-merge 1 2 3 4 2>/dev/null || true
test_commit after-merge 2>/dev/null || true
git checkout 1 -b L 2>/dev/null || true
test_commit left 2>/dev/null || true
git checkout 4 -b crossover 2>/dev/null || true
test_commit after-4 2>/dev/null || true
git checkout initial -b more-L 2>/dev/null || true
test_commit after-initial 2>/dev/null || true
cat >expect.colors <<-\EOF 2>/dev/null || true

true
