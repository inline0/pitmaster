#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit first 2>/dev/null || true
git init new 2>/dev/null || true
git checkout -b openpgp-signing main 2>/dev/null || true
echo "Content for OpenPGP signing." >file-sign 2>/dev/null || true
git add file-sign 2>/dev/null || true
git commit -S -m "OpenPGP signed commit" 2>/dev/null || true
OPENPGP_SIGNING=$(git rev-parse --verify openpgp-signing) 2>/dev/null || true
IMPORTED=$(git -C new rev-parse --verify refs/heads/openpgp-signing) 2>/dev/null || true

true
