#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git add a.txt b.txt delete.txt 2>/dev/null || true
git commit -m base 2>/dev/null || true
echo a >a.txt 2>/dev/null || true
echo b >b.txt 2>/dev/null || true
echo delete >delete.txt 2>/dev/null || true
git add a.txt b.txt delete.txt 2>/dev/null || true
git commit -m tip 2>/dev/null || true
git tag tip 2>/dev/null || true
git reset --hard HEAD^ 2>/dev/null || true
echo change >a.txt 2>/dev/null || true
git commit -a -m side 2>/dev/null || true
git tag side 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
git merge --abort 2>/dev/null || true
git reset --hard side 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
git merge --abort 2>/dev/null || true

true
