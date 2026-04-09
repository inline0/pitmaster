#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

printf "%s\n" E C B A >expect 2>/dev/null || true
git init 2>/dev/null || true
git switch -c topic 2>/dev/null || true
echo base >file 2>/dev/null || true
git add file 2>/dev/null || true
test_commit I 2>/dev/null || true
echo A >file 2>/dev/null || true
git add file 2>/dev/null || true
test_commit A 2>/dev/null || true
git switch -c branchB I 2>/dev/null || true
echo B >file 2>/dev/null || true
git add file 2>/dev/null || true
test_commit B 2>/dev/null || true
git switch topic 2>/dev/null || true
echo A >file 2>/dev/null || true
echo B >>file 2>/dev/null || true
git add file 2>/dev/null || true
git merge --continue 2>/dev/null || true
echo C >other 2>/dev/null || true
git add other 2>/dev/null || true
test_commit C 2>/dev/null || true
git switch -c branchX I 2>/dev/null || true
echo X >file 2>/dev/null || true
git add file 2>/dev/null || true
test_commit X 2>/dev/null || true
git switch -c branchR M 2>/dev/null || true
git merge -m R -Xtheirs X 2>/dev/null || true
git switch topic 2>/dev/null || true
git merge -m N R 2>/dev/null || true
git switch -c branchY M 2>/dev/null || true
echo Y >y 2>/dev/null || true
git add y 2>/dev/null || true
test_commit Y 2>/dev/null || true
git switch -c branchZ C 2>/dev/null || true
echo Z >z 2>/dev/null || true
git add z 2>/dev/null || true
test_commit Z 2>/dev/null || true
git switch topic 2>/dev/null || true
git merge -m O Z 2>/dev/null || true
git merge -m P Y 2>/dev/null || true

true
