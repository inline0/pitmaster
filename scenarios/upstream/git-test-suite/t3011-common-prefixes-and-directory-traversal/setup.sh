#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit hello 2>/dev/null || true
mkdir untracked_dir 2>/dev/null || true
git init untracked_repo 2>/dev/null || true
cat <<-EOF >.gitignore 2>/dev/null || true
mkdir an_ignored_dir 2>/dev/null || true
mkdir an_untracked_dir 2>/dev/null || true
cat <<-EOF >expect 2>/dev/null || true
cat <<-EOF >expect 2>/dev/null || true

true
