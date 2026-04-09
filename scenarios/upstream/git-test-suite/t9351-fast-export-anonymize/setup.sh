#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit base 2>/dev/null || true
test_commit foo 2>/dev/null || true
test_commit retain-me 2>/dev/null || true
git checkout -b other HEAD^ 2>/dev/null || true
mkdir subdir 2>/dev/null || true
test_commit subdir/bar 2>/dev/null || true
test_commit subdir/xyzzy 2>/dev/null || true
git update-index --add --cacheinfo 160000,$fake_commit,link1 2>/dev/null || true
git update-index --add --cacheinfo 160000,$fake_commit,link2 2>/dev/null || true
git commit -m "add gitlink" 2>/dev/null || true
git tag -m "annotated tag" mytag 2>/dev/null || true
git tag -m "annotated tag with long message" longtag 2>/dev/null || true

true
