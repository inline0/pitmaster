#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit initial grep.h " 2>/dev/null || true
test_commit add-user revision.c " 2>/dev/null || true
mkdir subdir 2>/dev/null || true
test_commit subdir subdir/grep.c "enum grep_pat_token" 2>/dev/null || true
test_commit uninteresting unrelated "hello, world" 2>/dev/null || true
echo GREP_PATTERN >untracked 2>/dev/null || true
cat >$less <<-\EOF 2>/dev/null || true
printf "%s\n" "$@" >pager-args 2>/dev/null || true
chmod +x $less 2>/dev/null || true
cat >expect.less <<-\EOF 2>/dev/null || true
echo grep.h >expect.notless 2>/dev/null || true
rm -f expect.less pager-args out 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
(
)
cat >less <<-\EOF 2>/dev/null || true
printf "%s\n" "$@" >actual 2>/dev/null || true
chmod +x less 2>/dev/null || true

true
