#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mkdir path2 path3 pathx 2>/dev/null || true
git update-index --add -- path0 path?/file? pathx/ju path7 path8 path9 path10 2>/dev/null || true
git init submod1 2>/dev/null || true
git init submod2 2>/dev/null || true
git update-index --add submod[12] 2>/dev/null || true
git commit --allow-empty -m "empty 1 (updated)" 2>/dev/null || true
ln -s frotz path3 2>/dev/null || true
ln -s nitfol path5 2>/dev/null || true
mkdir -p path0 path1 path6 pathx/ju 2>/dev/null || true
touch path10 2>/dev/null || true
cat >.expected <<-\EOF 2>/dev/null || true

true
