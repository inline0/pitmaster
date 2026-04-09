#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config --unset fetch.recurseSubmodules 2>/dev/null || true
git config --unset fetch.recurseSubmodules 2>/dev/null || true
echo a > file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m "new file" 2>/dev/null || true
git config fetch.recurseSubmodules true 2>/dev/null || true
git config --unset fetch.recurseSubmodules 2>/dev/null || true

true
