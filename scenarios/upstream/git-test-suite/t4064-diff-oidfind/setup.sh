#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git commit --allow-empty -m "empty initial commit" 2>/dev/null || true
echo "Hello, world!" >greeting 2>/dev/null || true
git add greeting 2>/dev/null || true
git commit -m "add the greeting blob" && # borrowed from Git from the Bottom Up 2>/dev/null || true
git tag -m "the blob" greeting $(git rev-parse HEAD:greeting) 2>/dev/null || true
echo asdf >unrelated 2>/dev/null || true
git add unrelated 2>/dev/null || true
git commit -m "unrelated history" 2>/dev/null || true
git revert HEAD^ 2>/dev/null || true
git commit --allow-empty -m "another unrelated commit" 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
mkdir a 2>/dev/null || true
echo asdf >a/file 2>/dev/null || true
git add a/file 2>/dev/null || true
git commit -m "add a file in a subdirectory" 2>/dev/null || true

true
