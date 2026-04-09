#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init sub 2>/dev/null || true
git checkout -b B2 2>/dev/null || true
echo B2 >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m file 2>/dev/null || true
git checkout -b B1 2>/dev/null || true
echo B1 >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m file 2>/dev/null || true
git init various 2>/dev/null || true
git checkout -b B1 2>/dev/null || true
mkdir a c e 2>/dev/null || true
echo a/a >a/a 2>/dev/null || true
echo b >b 2>/dev/null || true
echo c/c >c/c 2>/dev/null || true
echo e/e >e/e 2>/dev/null || true
git submodule add ../sub f 2>/dev/null || true
git submodule add ../sub g 2>/dev/null || true
echo "B1 i" >i 2>/dev/null || true
git submodule add -b B1 ../sub k 2>/dev/null || true
mkdir l 2>/dev/null || true
echo l/l >l/l 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m B1 2>/dev/null || true
git checkout -b B2 2>/dev/null || true
git rm -rf :^.gitmodules :^k 2>/dev/null || true
mkdir b d f 2>/dev/null || true
echo a >a 2>/dev/null || true
echo b/b >b/b 2>/dev/null || true
echo d/d >d/d 2>/dev/null || true
git submodule add ../sub e 2>/dev/null || true
echo f/f >f/f 2>/dev/null || true
git submodule add ../sub h 2>/dev/null || true
echo "B2 i" >i 2>/dev/null || true
mkdir m 2>/dev/null || true
echo m/m >m/m 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m B2 2>/dev/null || true
git checkout --recurse-submodules B1 2>/dev/null || true
git init super 2>/dev/null || true
git init sub 2>/dev/null || true
test_commit -C sub A 2>/dev/null || true
test_commit -C sub B 2>/dev/null || true
git submodule add ./sub 2>/dev/null || true
git commit -m sub 2>/dev/null || true

true
