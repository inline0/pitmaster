#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit one 2>/dev/null || true
INITIAL=$(git rev-parse HEAD) 2>/dev/null || true
git init submodule 2>/dev/null || true
test_commit two 2>/dev/null || true
git submodule add ./submodule 2>/dev/null || true
git commit -m first 2>/dev/null || true
test_commit three 2>/dev/null || true
git add submodule 2>/dev/null || true
git commit -m second 2>/dev/null || true
SECOND=$(git rev-parse HEAD) 2>/dev/null || true
git mv two.t four.t 2>/dev/null || true
git commit -m "second submodule" 2>/dev/null || true
test_commit four 2>/dev/null || true
git add submodule 2>/dev/null || true
git commit --amend --no-edit 2>/dev/null || true
THIRD=$(git rev-parse HEAD) 2>/dev/null || true
git submodule update --init 2>/dev/null || true

true
