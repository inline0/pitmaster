#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit first 2>/dev/null || true
git init new 2>/dev/null || true
git tag -s -m "OpenPGP signed tag" openpgp-signed first 2>/dev/null || true
OPENPGP_SIGNED=$(git rev-parse --verify refs/tags/openpgp-signed) 2>/dev/null || true

true
