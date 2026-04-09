#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit --author Frigate\ \<flying@over.world\> \ 2>/dev/null || true
echo "Test 1" >>foo 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -a -C Initial 2>/dev/null || true
echo "Test 2" >>foo 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -a -C Initial --reset-author 2>/dev/null || true
echo "author $GIT_AUTHOR_NAME <$GIT_AUTHOR_EMAIL> $GIT_AUTHOR_DATE" >expect 2>/dev/null || true
echo "Test 3" >>foo 2>/dev/null || true
test_tick 2>/dev/null || true

true
