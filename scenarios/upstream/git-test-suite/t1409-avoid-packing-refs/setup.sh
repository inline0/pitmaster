#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git commit --allow-empty -m "Commit A" 2>/dev/null || true
A=$(git rev-parse HEAD) 2>/dev/null || true
git commit --allow-empty -m "Commit B" 2>/dev/null || true
B=$(git rev-parse HEAD) 2>/dev/null || true
git commit --allow-empty -m "Commit C" 2>/dev/null || true
C=$(git rev-parse HEAD) 2>/dev/null || true
git update-ref refs/heads/foo $A 2>/dev/null || true
git update-ref refs/heads/foo $B 2>/dev/null || true
git update-ref refs/heads/foo $C $B 2>/dev/null || true
git update-ref -d refs/heads/foo 2>/dev/null || true
git pack-refs --all 2>/dev/null || true
git pack-refs --all 2>/dev/null || true

true
