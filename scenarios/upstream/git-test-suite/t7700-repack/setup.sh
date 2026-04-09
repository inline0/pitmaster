#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo content1 >file1 2>/dev/null || true
echo content2 >file2 2>/dev/null || true
git add . 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m initial_commit 2>/dev/null || true
mv pack-* .git/objects/pack/ 2>/dev/null || true
mkdir alt_objects 2>/dev/null || true
echo $(pwd)/alt_objects >.git/objects/info/alternates 2>/dev/null || true
echo content3 >file3 2>/dev/null || true
git add file3 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m commit_file3 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit -C repo A 2>/dev/null || true
(
cd repo 2>/dev/null || true
ln -s objects .git/alt_objects 2>/dev/null || true
echo "$(pwd)/.git/alt_objects" >.git/objects/info/alternates 2>/dev/null || true
)

true
