#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo .gitignore >.gitignore 2>/dev/null || true
echo actual >>.gitignore 2>/dev/null || true
echo expect >>.gitignore 2>/dev/null || true
mkdir dir 2>/dev/null || true
echo x >dir/file1 2>/dev/null || true
echo y >dir/file2 2>/dev/null || true
git add dir 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git tag initial 2>/dev/null || true
ln -s dir symlink 2>/dev/null || true
echo "?? symlink" >expect 2>/dev/null || true
mkdir copy 2>/dev/null || true
cp dir/file1 copy/file1 2>/dev/null || true
echo "changed in copy" >copy/file2 2>/dev/null || true
git add copy 2>/dev/null || true
git commit -m second 2>/dev/null || true
ln -s dir copy 2>/dev/null || true
echo " D copy/file1" >expect 2>/dev/null || true
echo " D copy/file2" >>expect 2>/dev/null || true
echo "?? copy" >>expect 2>/dev/null || true

true
