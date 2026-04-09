#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mkdir -p sub/dir work 2>/dev/null || true
cp -R .git repo.git 2>/dev/null || true
git checkout -B main 2>/dev/null || true
test_commit abc 2>/dev/null || true
git checkout -b side 2>/dev/null || true
test_commit def 2>/dev/null || true
git checkout main 2>/dev/null || true
git worktree add worktree side 2>/dev/null || true

true
