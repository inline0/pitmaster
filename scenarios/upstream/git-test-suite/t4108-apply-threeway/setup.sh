#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git reset --hard 2>/dev/null || true
git checkout main^0 2>/dev/null || true
echo "* merge=union" >.gitattributes 2>/dev/null || true
rm .gitattributes 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout main^0 2>/dev/null || true
test_write_lines 1 two 3 4 five six 7 >one 2>/dev/null || true
cat one >expect 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout main^0 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout -b adder 2>/dev/null || true
test_write_lines 1 2 3 4 5 6 7 >three 2>/dev/null || true
test_write_lines 1 2 3 4 5 6 7 >four 2>/dev/null || true
git add three four 2>/dev/null || true
git commit -m "add three and four" 2>/dev/null || true
git checkout -b another adder^ 2>/dev/null || true
test_write_lines 1 2 3 4 5 6 7 >three 2>/dev/null || true
test_write_lines 1 2 3 four 5 6 7 >four 2>/dev/null || true
git add three four 2>/dev/null || true
git commit -m "add three and four" 2>/dev/null || true
git checkout adder^0 2>/dev/null || true

true
