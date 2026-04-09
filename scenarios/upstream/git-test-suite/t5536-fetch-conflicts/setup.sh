#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git commit --allow-empty -m "Initial" 2>/dev/null || true
git branch branch1 2>/dev/null || true
git tag tag1 2>/dev/null || true
git commit --allow-empty -m "First" 2>/dev/null || true
git branch branch2 2>/dev/null || true
git tag tag2 2>/dev/null || true

true
