#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config set --global protocol.file.allow always 2>/dev/null || true
git config set --global core.logAllRefUpdates false 2>/dev/null || true
git init parent 2>/dev/null || true
test_commit parent 2>/dev/null || true
git init --ref-format=$OTHER_FORMAT submodule 2>/dev/null || true
test_commit -C submodule submodule 2>/dev/null || true
git submodule add ./submodule 2>/dev/null || true
git init submodule 2>/dev/null || true
test_commit -C submodule submodule-initial 2>/dev/null || true
git init upstream 2>/dev/null || true

true
