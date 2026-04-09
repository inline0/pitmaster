#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config --global protocol.file.allow always 2>/dev/null || true
git init upstream 2>/dev/null || true
mv upstream ./-upstream 2>/dev/null || true
git submodule add ./-upstream sub 2>/dev/null || true
git add sub .gitmodules 2>/dev/null || true
git commit -m submodule 2>/dev/null || true
echo base >expect 2>/dev/null || true
git init --bare dst 2>/dev/null || true
sed "s|\./||" .gitmodules >.gitmodules.munged 2>/dev/null || true
mv .gitmodules.munged .gitmodules 2>/dev/null || true
git commit -am "drop protection" 2>/dev/null || true
git init --bare dst 2>/dev/null || true
git init testmodule 2>/dev/null || true
test_commit -C testmodule c 2>/dev/null || true
git submodule add ./testmodule 2>/dev/null || true
sed -e "s|\\(submodule \"testmodule\\)\"|\\1\\\\\\\\\"|" \ 2>/dev/null || true
mv .new .gitmodules 2>/dev/null || true
git commit -am "Add testmodule" 2>/dev/null || true

true
