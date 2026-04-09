#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

test_commit A 2>/dev/null || true
test_commit B 2>/dev/null || true
git add S* 2>/dev/null || true
test_commit C 2>/dev/null || true
echo dirty >"$d"/A.t || return 1 2>/dev/null || true
echo dirty >"$d/C2.t" || return 1 2>/dev/null || true
git init submodule 2>/dev/null || true
test_commit initial 2>/dev/null || true
COMMIT=$(git rev-parse HEAD) 2>/dev/null || true
BLOB=$(git hash-object -w --stdin <gitmodules) 2>/dev/null || true
printf "100644 blob $BLOB\t.gitmodules\n" >tree 2>/dev/null || true
TREE=$(git mktree <tree) 2>/dev/null || true
COMMIT=$(git commit-tree "$TREE") 2>/dev/null || true
git reset --hard "$COMMIT" 2>/dev/null || true
git init repo 2>/dev/null || true

true
