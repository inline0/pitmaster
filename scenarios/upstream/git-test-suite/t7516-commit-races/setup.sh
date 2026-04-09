#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git commit --allow-empty -m hare 2>/dev/null || true
git commit --allow-empty -m airplane 2>/dev/null || true
git checkout --orphan branch 2>/dev/null || true
git commit --allow-empty -m base 2>/dev/null || true

true
