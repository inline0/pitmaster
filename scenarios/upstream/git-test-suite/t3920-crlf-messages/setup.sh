#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit initial 2>/dev/null || true
printf "  " >>expect 2>/dev/null || true
cat .crlf-subject-${branch}.txt >>expect 2>/dev/null || true
printf "\n" >>expect || return 1 2>/dev/null || true
git branch -v >tmp 2>/dev/null || true
cat .crlf-subject-${branch}.txt >expect 2>/dev/null || true
printf "\n" >>expect 2>/dev/null || true

true
