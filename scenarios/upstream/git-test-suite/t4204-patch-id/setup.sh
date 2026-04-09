#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_write_lines $str >foo 2>/dev/null || true
test_write_lines $str >bar 2>/dev/null || true
git add foo bar 2>/dev/null || true
git commit -a -m initial 2>/dev/null || true
test_write_lines $str b >foo 2>/dev/null || true
test_write_lines $str b >bar 2>/dev/null || true
git commit -a -m first 2>/dev/null || true
git checkout -b same main 2>/dev/null || true
git commit --amend -m same-msg 2>/dev/null || true
git checkout -b notsame main 2>/dev/null || true
echo c >foo 2>/dev/null || true
echo c >bar 2>/dev/null || true
git commit --amend -a -m notsame-msg 2>/dev/null || true
git checkout -b with_space main~ 2>/dev/null || true
cat >foo <<-\EOF 2>/dev/null || true
cp foo bar 2>/dev/null || true
git add foo bar 2>/dev/null || true
git commit --amend -m "with spaces" 2>/dev/null || true
test_write_lines bar foo >bar-then-foo 2>/dev/null || true
test_write_lines foo bar >foo-then-bar 2>/dev/null || true

true
