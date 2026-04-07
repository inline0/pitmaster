#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo original >file
git add file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git tag initial 2>/dev/null || true
echo modified >file
git add file 2>/dev/null || true
git add file 2>/dev/null || true
mkdir d
git add f1 d/f2 2>/dev/null || true
echo "-rw-------" >f1_mode.expected
echo "drwx------" >d_mode.expected
echo true >script.sh
git add --chmod=+x script.sh 2>/dev/null || true
echo true >>script.sh
git add --chmod=-x mode_test 2>/dev/null || true
git tag mode_test_forward_initial 2>/dev/null || true
echo content >>mode_test
git tag mode_test_reverse_initial 2>/dev/null || true
git add --chmod=+x mode_test 2>/dev/null || true
git add --chmod=+x mode_test 2>/dev/null || true
git add --chmod=+x change_x_to_notx 2>/dev/null || true
git add --chmod=-x change_x_to_notx 2>/dev/null || true
git rm change_x_to_notx 2>/dev/null || true
git tag change_x_to_notx_initial 2>/dev/null || true
git add --chmod=-x change_notx_to_x 2>/dev/null || true
git add --chmod=+x change_notx_to_x 2>/dev/null || true
git rm change_notx_to_x 2>/dev/null || true
git tag change_notx_to_x_initial 2>/dev/null || true
echo content >non-canon
git add non-canon 2>/dev/null || true
echo content >non-canon
git add non-canon 2>/dev/null || true
