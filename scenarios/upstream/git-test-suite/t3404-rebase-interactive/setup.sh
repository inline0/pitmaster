#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config set gc.reflogExpire never 2>/dev/null || true
git config set gc.reflogExpireUnreachable never 2>/dev/null || true
git switch -C primary 2>/dev/null || true
test_commit A file1 2>/dev/null || true
test_commit B file1 2>/dev/null || true
test_commit C file2 2>/dev/null || true
test_commit D file1 2>/dev/null || true
test_commit E file3 2>/dev/null || true
git checkout -b branch1 A 2>/dev/null || true
test_commit F file4 2>/dev/null || true
test_commit G file1 2>/dev/null || true
test_commit H file5 2>/dev/null || true
git checkout -b branch2 F 2>/dev/null || true
test_commit I file6 2>/dev/null || true
git checkout -b conflict-branch A 2>/dev/null || true
test_commit one conflict 2>/dev/null || true
test_commit two conflict 2>/dev/null || true
test_commit three conflict 2>/dev/null || true
test_commit four conflict 2>/dev/null || true
git checkout -b no-conflict-branch A 2>/dev/null || true
test_commit J fileJ 2>/dev/null || true
test_commit K fileK 2>/dev/null || true
test_commit L fileL 2>/dev/null || true
test_commit M fileM 2>/dev/null || true
git checkout -b no-ff-branch A 2>/dev/null || true
test_commit N fileN 2>/dev/null || true
test_commit O fileO 2>/dev/null || true
test_commit P fileP 2>/dev/null || true
git checkout -b emptybranch primary 2>/dev/null || true
git commit --allow-empty -m "empty" 2>/dev/null || true
git rebase --keep-empty -i HEAD~2 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
git rebase -i HEAD^ >output 2>&1 2>/dev/null || true

true
