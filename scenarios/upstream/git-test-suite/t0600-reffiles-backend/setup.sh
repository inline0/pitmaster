#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git commit --allow-empty -m Initial 2>/dev/null || true
C=$(git rev-parse HEAD) 2>/dev/null || true
git commit --allow-empty -m Second 2>/dev/null || true
D=$(git rev-parse HEAD) 2>/dev/null || true
git commit --allow-empty -m Third 2>/dev/null || true
E=$(git rev-parse HEAD) 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
git pack-refs --all 2>/dev/null || true
mkdir -p .git/$prefix/foo/bar/baz 2>/dev/null || true
echo "$C" >expected 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
git pack-refs --all 2>/dev/null || true
mkdir -p .git/$prefix/foo/bar/baz 2>/dev/null || true

true
