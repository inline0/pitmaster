#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config --global protocol.file.allow always 2>/dev/null || true
git checkout -b main 2>/dev/null || true
test_commit commit1 2>/dev/null || true
mkdir sub 2>/dev/null || true
git init 2>/dev/null || true
test_commit subcommit1 2>/dev/null || true
git tag sub_when_added_to_super 2>/dev/null || true
git branch other 2>/dev/null || true
git submodule add "file://$pwd/sub" sub 2>/dev/null || true
git commit -m "add submodule" 2>/dev/null || true
test_commit subcommit2 2>/dev/null || true

true
