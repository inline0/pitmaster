#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git checkout --orphan _p 2>/dev/null || true
test_commit A 2>/dev/null || true
test_commit B 2>/dev/null || true
git checkout -b _q @^ && test_commit C 2>/dev/null || true
git checkout -b _r @^ && test_commit D 2>/dev/null || true
git checkout _p && git merge --no-ff _q _r -m E 2>/dev/null || true
git checkout _r && test_commit F 2>/dev/null || true
git checkout _p && git merge --no-ff _r -m G 2>/dev/null || true
git checkout @^^ && git merge --no-ff _p -m H 2>/dev/null || true
git checkout --orphan 0_p && test_commit 0_A 2>/dev/null || true
git checkout -b 0_q 0_p && test_commit 0_B 2>/dev/null || true
git checkout -b 0_r 0_p 2>/dev/null || true
test_commit 0_C 2>/dev/null || true
test_commit 0_D 2>/dev/null || true
git checkout -b 0_s 0_p && test_commit 0_E 2>/dev/null || true
git checkout -b 0_t 0_p && git merge --no-ff 0_r^ 0_s -m 0_F 2>/dev/null || true
git checkout 0_p && git merge --no-ff 0_s -m 0_G 2>/dev/null || true
git checkout @^ && git merge --no-ff 0_q 0_r 0_t 0_p -m 0_H 2>/dev/null || true
git checkout --orphan 1_p 2>/dev/null || true
test_commit 1_A 2>/dev/null || true
test_commit 1_B 2>/dev/null || true
test_commit 1_C 2>/dev/null || true
git checkout -b 1_q @^ && test_commit 1_D 2>/dev/null || true
git checkout 1_p && git merge --no-ff 1_q -m 1_E 2>/dev/null || true
git checkout -b 1_r @~3 && test_commit 1_F 2>/dev/null || true
git checkout 1_p && git merge --no-ff 1_r -m 1_G 2>/dev/null || true
git checkout @^^ && git merge --no-ff 1_p -m 1_H 2>/dev/null || true

true
