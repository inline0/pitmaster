#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit init 2>/dev/null || true
git worktree add source 2>/dev/null || true
git worktree list --porcelain >out 2>/dev/null || true
cat <<-EOF >expected 2>/dev/null || true
git worktree lock --reason hahaha source 2>/dev/null || true
echo hahaha >expected 2>/dev/null || true

true
