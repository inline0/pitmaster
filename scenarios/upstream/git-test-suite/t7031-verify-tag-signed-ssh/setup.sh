#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo 1 >file && git add file 2>/dev/null || true
test_tick && git commit -m initial 2>/dev/null || true
git tag -s -m initial initial 2>/dev/null || true
git branch side 2>/dev/null || true
echo 2 >file && test_tick && git commit -a -m second 2>/dev/null || true
git tag -s -m second second 2>/dev/null || true
git checkout side 2>/dev/null || true
echo 3 >elif && git add elif 2>/dev/null || true
test_tick && git commit -m "third on side" 2>/dev/null || true
git checkout main 2>/dev/null || true
test_tick && git merge -S side 2>/dev/null || true
git tag -s -m merge merge 2>/dev/null || true
echo 4 >file && test_tick && git commit -a -S -m "fourth unsigned" 2>/dev/null || true
git tag -a -m fourth-unsigned fourth-unsigned 2>/dev/null || true
test_tick && git commit --amend -S -m "fourth signed" 2>/dev/null || true
git tag -s -m fourth fourth-signed 2>/dev/null || true
echo 5 >file && test_tick && git commit -a -m "fifth" 2>/dev/null || true
git tag fifth-unsigned 2>/dev/null || true
git config commit.gpgsign true 2>/dev/null || true
echo 6 >file && test_tick && git commit -a -m "sixth" 2>/dev/null || true
git tag -a -m sixth sixth-unsigned 2>/dev/null || true
test_tick && git rebase -f HEAD^^ && git tag -s -m 6th sixth-signed HEAD^ 2>/dev/null || true
git tag -m seventh -s seventh-signed 2>/dev/null || true
echo 8 >file && test_tick && git commit -a -m eighth 2>/dev/null || true
git tag -u"${GPGSSH_KEY_UNTRUSTED}" -m eighth eighth-signed-alt 2>/dev/null || true
echo expired >file && test_tick && git commit -a -m expired -S"${GPGSSH_KEY_EXPIRED}" 2>/dev/null || true
git tag -s -u "${GPGSSH_KEY_EXPIRED}" -m expired-signed expired-signed 2>/dev/null || true
echo notyetvalid >file && test_tick && git commit -a -m notyetvalid -S"${GPGSSH_KEY_NOTYETVALID}" 2>/dev/null || true
git tag -s -u "${GPGSSH_KEY_NOTYETVALID}" -m notyetvalid-signed notyetvalid-signed 2>/dev/null || true
echo timeboxedvalid >file && test_tick && git commit -a -m timeboxedvalid -S"${GPGSSH_KEY_TIMEBOXEDVALID}" 2>/dev/null || true
git tag -s -u "${GPGSSH_KEY_TIMEBOXEDVALID}" -m timeboxedvalid-signed timeboxedvalid-signed 2>/dev/null || true
echo timeboxedinvalid >file && test_tick && git commit -a -m timeboxedinvalid -S"${GPGSSH_KEY_TIMEBOXEDINVALID}" 2>/dev/null || true
git tag -s -u "${GPGSSH_KEY_TIMEBOXEDINVALID}" -m timeboxedinvalid-signed timeboxedinvalid-signed 2>/dev/null || true

true
