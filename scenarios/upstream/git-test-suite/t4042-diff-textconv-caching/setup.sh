#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo foo content 1 >foo.bin 2>/dev/null || true
echo bar content 1 >bar.bin 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo foo content 2 >foo.bin 2>/dev/null || true
echo bar content 2 >bar.bin 2>/dev/null || true
git commit -a -m two 2>/dev/null || true
echo "*.bin diff=magic" >.gitattributes 2>/dev/null || true
git config diff.magic.textconv ./helper 2>/dev/null || true
git config diff.magic.cachetextconv true 2>/dev/null || true

true
