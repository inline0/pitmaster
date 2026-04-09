#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init repo 2>/dev/null || true
(
cd repo 2>/dev/null || true
echo "initial" >a 2>/dev/null || true
mkdir folder1 folder2 deep 2>/dev/null || true
mkdir deep/deeper1 deep/deeper2 2>/dev/null || true
mkdir deep/deeper1/deepest 2>/dev/null || true
cp a folder1 2>/dev/null || true
cp a folder2 2>/dev/null || true
cp a deep 2>/dev/null || true
cp a deep/deeper1 2>/dev/null || true
cp a deep/deeper2 2>/dev/null || true
cp a deep/deeper1/deepest 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "initial commit" 2>/dev/null || true
)
rm repo/.git/info/sparse-checkout 2>/dev/null || true
cat >repo/.git/info/sparse-checkout <<-\EOF 2>/dev/null || true
cp repo/.git/info/sparse-checkout expect 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true

true
