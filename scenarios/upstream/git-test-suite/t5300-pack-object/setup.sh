#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

rm -f .git/index* 2>/dev/null || true
git update-index --add a a_big b b_big c 2>/dev/null || true
cat c >d && echo foo >>d && git update-index --add d 2>/dev/null || true
git init pack-object-stdin 2>/dev/null || true
test_commit -C pack-object-stdin one 2>/dev/null || true
test_commit -C pack-object-stdin two 2>/dev/null || true
cat >in <<-EOF 2>/dev/null || true
cat >in <<-EOF 2>/dev/null || true
sed "s/^> //g" >err.expect <<-EOF 2>/dev/null || true
cat >err.expect <<-EOF 2>/dev/null || true
cat >in <<-EOF 2>/dev/null || true
sed -e "s/^> //g" -e "s/Z$//g" >err.expect <<-EOF 2>/dev/null || true

true
