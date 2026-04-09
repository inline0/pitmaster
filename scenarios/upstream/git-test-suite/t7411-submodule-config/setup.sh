#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config --global protocol.file.allow always 2>/dev/null || true
mkdir submodule 2>/dev/null || true
git init 2>/dev/null || true
echo a >a 2>/dev/null || true
git add . 2>/dev/null || true
git commit -ma 2>/dev/null || true
mkdir super 2>/dev/null || true
git init 2>/dev/null || true
git submodule add ../submodule 2>/dev/null || true
git submodule add ../submodule a 2>/dev/null || true
git commit -m "add as submodule and as a" 2>/dev/null || true
git mv a b 2>/dev/null || true
git commit -m "move a to b" 2>/dev/null || true
test_create_repo repo 2>/dev/null || true
cat >repo/.gitmodules <<-\EOF 2>/dev/null || true

true
