#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config --global protocol.file.allow always 2>/dev/null || true
echo file > file 2>/dev/null || true
git add file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m upstream 2>/dev/null || true
git submodule add ../submodule submodule 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "submodule" 2>/dev/null || true
git submodule init submodule 2>/dev/null || true
echo "line2" > file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m "Commit 2" 2>/dev/null || true
git add submodule 2>/dev/null || true
git commit -m "submodule update" 2>/dev/null || true
git submodule add ../rebasing rebasing 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "rebasing" 2>/dev/null || true
git submodule add ../merging merging 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "rebasing" 2>/dev/null || true
git submodule add ../none none 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "none" 2>/dev/null || true
git submodule add ../super super 2>/dev/null || true
test_create_repo main-branch-submodule 2>/dev/null || true
test_commit -C main-branch-submodule initial 2>/dev/null || true
test_create_repo main-branch 2>/dev/null || true
test_commit -C main-branch-submodule world 2>/dev/null || true
git reset --hard HEAD~1 2>/dev/null || true
git submodule update submodule 2>/dev/null || true

true
