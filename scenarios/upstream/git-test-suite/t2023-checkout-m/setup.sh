#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

cp each.txt each.txt.conflicted 2>/dev/null || true
echo resolved >each.txt 2>/dev/null || true
git add each.txt 2>/dev/null || true
git checkout -m -- each.txt 2>/dev/null || true
cp both.txt both.txt.conflicted 2>/dev/null || true
echo resolved >both.txt 2>/dev/null || true
git add both.txt 2>/dev/null || true
git checkout -m -- both.txt 2>/dev/null || true
git init co-force 2>/dev/null || true
echo a >a 2>/dev/null || true
git add a 2>/dev/null || true
git commit -ama 2>/dev/null || true
A_OBJ=$(git rev-parse :a) 2>/dev/null || true
git branch topic 2>/dev/null || true
echo b >a 2>/dev/null || true
git commit -amb 2>/dev/null || true
B_OBJ=$(git rev-parse :a) 2>/dev/null || true
git checkout topic 2>/dev/null || true
echo c >a 2>/dev/null || true
C_OBJ=$(git hash-object a) 2>/dev/null || true
git checkout -m main 2>/dev/null || true
git checkout -f topic 2>/dev/null || true

true
