#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init sub1 2>/dev/null || true
test_commit -C sub1 first 2>/dev/null || true
git submodule add ./sub1 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m superproject 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
git submodule absorbgitdirs 2>actual 2>/dev/null || true
git submodule deinit --all 2>/dev/null || true
git submodule absorbgitdirs 2>err 2>/dev/null || true

true
