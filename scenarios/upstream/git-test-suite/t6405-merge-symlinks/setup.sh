#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config core.symlinks false 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git branch b-symlink 2>/dev/null || true
git branch b-file 2>/dev/null || true
git commit -m main 2>/dev/null || true
git checkout b-symlink 2>/dev/null || true
git commit -m b-symlink 2>/dev/null || true
git checkout b-file 2>/dev/null || true
echo plain-file >symlink 2>/dev/null || true
git add symlink 2>/dev/null || true
git commit -m b-file 2>/dev/null || true
git checkout b-symlink 2>/dev/null || true

true
