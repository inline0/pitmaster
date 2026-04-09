#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mkdir -p a/b/c a/b-2/c 2>/dev/null || true
git add -A 2>/dev/null || true
git commit -m base 2>/dev/null || true
git tag start 2>/dev/null || true
git add -A 2>/dev/null || true
git commit -m "dir to symlink" 2>/dev/null || true
git checkout HEAD^0 2>/dev/null || true
git reset --hard main 2>/dev/null || true
git rm --cached a/b 2>/dev/null || true
git commit -m "untracked symlink remains" 2>/dev/null || true
git checkout HEAD^0 2>/dev/null || true
git reset --hard main 2>/dev/null || true
git rm --cached a/b 2>/dev/null || true
git commit -m "untracked symlink remains" 2>/dev/null || true
git checkout -f start^0 2>/dev/null || true

true
