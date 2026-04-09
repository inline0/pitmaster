#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit initial 2>/dev/null || true
mkdir f$i 2>/dev/null || true
mkdir f$i/f$j 2>/dev/null || true
echo $j >f$i/f$j/data.txt || return 1 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "Initialized trees" 2>/dev/null || true
git checkout -b topic$i main 2>/dev/null || true
echo change-$i >f$i/f$i/data.txt 2>/dev/null || true
git commit -a -m "Changed f$i/f$i/data.txt" || return 1 2>/dev/null || true
cat >packinput.txt <<-EOF 2>/dev/null || true

true
