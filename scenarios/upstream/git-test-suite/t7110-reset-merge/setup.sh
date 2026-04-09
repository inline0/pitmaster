#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git reset --merge HEAD^ 2>/dev/null || true
git reset --merge second 2>/dev/null || true
git reset --hard second 2>/dev/null || true
cat file1 >file2 2>/dev/null || true
git reset --keep HEAD^ 2>/dev/null || true
git reset --keep second 2>/dev/null || true
git reset --hard second 2>/dev/null || true
cat file1 >file2 2>/dev/null || true
echo "line 5" >> file1 2>/dev/null || true
git add file1 2>/dev/null || true
git reset --merge HEAD^ 2>/dev/null || true
git reset --hard initial 2>/dev/null || true
echo "line 5" >> file2 2>/dev/null || true
git add file2 2>/dev/null || true
git reset --merge second 2>/dev/null || true
git reset --hard second 2>/dev/null || true
echo "line 5" >> file1 2>/dev/null || true
git add file1 2>/dev/null || true
git reset --hard second 2>/dev/null || true
echo "line 4" >> file2 2>/dev/null || true
git add file2 2>/dev/null || true
git reset --merge HEAD^ 2>/dev/null || true
git reset --hard initial 2>/dev/null || true
git reset --merge second 2>/dev/null || true
git reset --hard second 2>/dev/null || true
echo "line 4" >> file2 2>/dev/null || true
git add file2 2>/dev/null || true
git reset --keep HEAD^ 2>/dev/null || true
git reset --keep second 2>/dev/null || true
git reset --hard second 2>/dev/null || true
echo "line 5" >> file1 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "add line 5" file1 2>/dev/null || true
sed -e "s/line 1/changed line 1/" <file1 >file3 2>/dev/null || true
mv file3 file1 2>/dev/null || true
git reset --hard second 2>/dev/null || true
echo "line 5" >> file1 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "add line 5" file1 2>/dev/null || true
sed -e "s/line 1/changed line 1/" <file1 >file3 2>/dev/null || true
mv file3 file1 2>/dev/null || true
git reset --hard second 2>/dev/null || true
git branch branch1 2>/dev/null || true
git branch branch2 2>/dev/null || true
git branch branch3 2>/dev/null || true
git checkout branch1 2>/dev/null || true
echo "line 5 in branch1" >> file1 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -a -m "change in branch1" 2>/dev/null || true
git checkout branch2 2>/dev/null || true
echo "line 5 in branch2" >> file1 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -a -m "change in branch2" 2>/dev/null || true
git tag third 2>/dev/null || true
git checkout branch3 2>/dev/null || true
echo a new file >file3 2>/dev/null || true
rm -f file1 2>/dev/null || true
git add file3 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -a -m "change in branch3" 2>/dev/null || true
git checkout third 2>/dev/null || true
git reset --merge HEAD^ 2>/dev/null || true

true
