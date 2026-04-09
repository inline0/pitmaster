#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config --global protocol.file.allow always 2>/dev/null || true
test_commit initial 2>/dev/null || true
git init upstream 2>/dev/null || true
test_commit -C upstream upstream submodule_file 2>/dev/null || true
git submodule add ./upstream a/sm 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m submodule 2>/dev/null || true
mkdir b 2>/dev/null || true
ln -s b a 2>/dev/null || true
mkdir b 2>/dev/null || true
ln -s b A 2>/dev/null || true

true
