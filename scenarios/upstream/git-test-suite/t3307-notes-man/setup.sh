#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit A 2>/dev/null || true
test_commit B 2>/dev/null || true
test_commit C 2>/dev/null || true
cat <<-\EOF >expect 2>/dev/null || true
git notes add -m "Acked-by: A C Ker <acker@example.com>" B 2>/dev/null || true
cp "$TEST_DIRECTORY"/test-binary-1.png . 2>/dev/null || true
git checkout B 2>/dev/null || true
git notes --ref=logo add -C "$blob" 2>/dev/null || true
git notes --ref=logo copy B C 2>/dev/null || true
git notes --ref=logo show C >actual 2>/dev/null || true

true
