#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init parent 2>/dev/null || true
test_commit -C parent "root-commit" 2>/dev/null || true
mkdir -p parent/link-to-dir 2>/dev/null || true
(
cd parent/link-to-dir 2>/dev/null || true
git init real-repo 2>/dev/null || true
ln -s real-repo/.git .git 2>/dev/null || true
echo .git >expect 2>/dev/null || true
)
mkdir -p parent/fifo-trap 2>/dev/null || true
(
cd parent/fifo-trap 2>/dev/null || true
)
mkdir -p parent/symlink-fifo-trap 2>/dev/null || true
(
cd parent/symlink-fifo-trap 2>/dev/null || true
ln -s target-fifo .git 2>/dev/null || true
)
mkdir -p parent/garbage-trap 2>/dev/null || true
(
cd parent/garbage-trap 2>/dev/null || true
echo "garbage" >.git 2>/dev/null || true
)

true
