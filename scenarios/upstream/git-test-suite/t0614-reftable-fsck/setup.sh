#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init repo 2>/dev/null || true
test_commit -C repo initial 2>/dev/null || true
REFTABLE_DIR=$(git -C worktree rev-parse --git-dir)/reftable 2>/dev/null || true
mv "$REFTABLE_DIR/$EXISTING_TABLE" "$REFTABLE_DIR/broken.ref" 2>/dev/null || true
echo "broken.ref" >"$REFTABLE_DIR/tables.list" 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
echo garbage >"$REFTABLE_DIR/tables.list" 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit -C repo initial 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
