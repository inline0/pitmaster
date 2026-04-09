#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo "text" >a 2>/dev/null || true
echo "text" >b 2>/dev/null || true
mkdir dir 2>/dev/null || true
echo "text" >dir/c 2>/dev/null || true
git init sub 2>/dev/null || true
mkdir A B 2>/dev/null || true
echo "text" >A/a 2>/dev/null || true
echo "text" >B/b 2>/dev/null || true
git add A B 2>/dev/null || true
git commit -m sub 2>/dev/null || true
git init sub2 2>/dev/null || true
echo "text" >a 2>/dev/null || true
git add a 2>/dev/null || true
git commit -m sub2 2>/dev/null || true
git submodule add ./sub 2>/dev/null || true
git submodule add ./sub2 2>/dev/null || true
git add a b dir 2>/dev/null || true
git commit -m super 2>/dev/null || true
git tag -am tag-to-commit tag-to-commit HEAD 2>/dev/null || true
git tag -am tag-to-tree tag-to-tree $tree 2>/dev/null || true
git branch -m main 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
echo "new-text" >b 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
git checkout main && git sparse-checkout init" 2>/dev/null || true
git checkout -b branchY main 2>/dev/null || true
test_commit modified-b-in-branchY b 2>/dev/null || true
git checkout -b branchX main 2>/dev/null || true
test_commit modified-b-in-branchX b 2>/dev/null || true

true
