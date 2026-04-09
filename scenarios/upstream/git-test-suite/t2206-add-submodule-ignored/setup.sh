#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config --global protocol.file.allow always 2>/dev/null || true
git init sub 2>/dev/null || true
test_commit sub_file1 2>/dev/null || true
git tag v1.0 2>/dev/null || true
test_commit sub_file2 2>/dev/null || true
git tag v2.0 2>/dev/null || true
test_commit sub_file3 2>/dev/null || true
git tag v3.0 2>/dev/null || true
git init main 2>/dev/null || true
test_commit first 2>/dev/null || true
git submodule add ../sub 2>/dev/null || true
git commit -m "add submodule" 2>/dev/null || true
git config -f .gitmodules submodule.sub.ignore all 2>/dev/null || true
git commit -m "update submodule config sub.ignore all" 2>/dev/null || true

true
