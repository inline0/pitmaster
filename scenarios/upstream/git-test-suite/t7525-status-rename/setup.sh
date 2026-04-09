#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo 1 >original 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m"Adding original file." 2>/dev/null || true
mv original renamed 2>/dev/null || true
echo 2 >> renamed 2>/dev/null || true
git add . 2>/dev/null || true
cat >.gitignore <<-\EOF 2>/dev/null || true

true
