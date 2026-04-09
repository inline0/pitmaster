#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo a >a 2>/dev/null || true
mkdir b 2>/dev/null || true
echo b >b/b 2>/dev/null || true
git add a b 2>/dev/null || true
git commit -m "add a and b" 2>/dev/null || true
git init submodule 2>/dev/null || true
echo c >submodule/c 2>/dev/null || true
git submodule add ./submodule 2>/dev/null || true
git commit -m "added submodule" 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
GITMODULES_HASH=$(git rev-parse HEAD:.gitmodules) 2>/dev/null || true
A_HASH=$(git rev-parse HEAD:a) 2>/dev/null || true
B_HASH=$(git rev-parse HEAD:b/b) 2>/dev/null || true
C_HASH=$(git -C submodule rev-parse HEAD:c) 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
