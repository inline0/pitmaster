#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo c0 > c0.c 2>/dev/null || true
git add c0.c 2>/dev/null || true
git commit -m c0 2>/dev/null || true
git tag c0 2>/dev/null || true
git reset --hard c0 2>/dev/null || true
echo c$i > c$i.c 2>/dev/null || true
git add c$i.c 2>/dev/null || true
git commit -m c$i 2>/dev/null || true
git tag c$i 2>/dev/null || true
git reset --hard c1 2>/dev/null || true
git merge $refs 2>/dev/null || true
git reset --hard c1 2>/dev/null || true
git merge c2 c3 c4 >actual 2>/dev/null || true

true
