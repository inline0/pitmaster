#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init remote 2>/dev/null || true
echo content >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo >&2 "proxying for $*" 2>/dev/null || true
echo >&2 "Running $cmd" 2>/dev/null || true
git config core.gitproxy ./proxy 2>/dev/null || true

true
