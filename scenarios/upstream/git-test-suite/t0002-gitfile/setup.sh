#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mv .git "$REAL" 2>/dev/null || true
echo "gitdir $REAL" >.git 2>/dev/null || true
echo "gitdir: $REAL.not" >.git 2>/dev/null || true
echo "gitdir: $REAL" >.git 2>/dev/null || true
echo "$REAL" >expect 2>/dev/null || true
echo "foo" >bar 2>/dev/null || true
SHA=$(git hash-object -w --stdin <bar) 2>/dev/null || true
rm -f "$REAL/objects/$(objpath $SHA)" 2>/dev/null || true
git update-index --add bar 2>/dev/null || true
SHA=$(git write-tree) 2>/dev/null || true
git update-ref "HEAD" "$SHA" 2>/dev/null || true
echo $SHA >expected 2>/dev/null || true
git init sgd 2>/dev/null || true
(
cd sgd 2>/dev/null || true
git config alias.lsfi ls-files 2>/dev/null || true
mv .git .realgit 2>/dev/null || true
echo "gitdir: .realgit" >.git 2>/dev/null || true
mkdir subdir 2>/dev/null || true
cd subdir 2>/dev/null || true
git add foo 2>/dev/null || true
echo foo >expected 2>/dev/null || true
)
test_create_repo enter_repo 2>/dev/null || true
(
cd enter_repo 2>/dev/null || true
test_tick 2>/dev/null || true
test_commit foo 2>/dev/null || true
mv .git .realgit 2>/dev/null || true
echo "gitdir: .realgit" >.git 2>/dev/null || true
)
cat >expected <<-EOF 2>/dev/null || true
(
cd enter_repo 2>/dev/null || true
git worktree add  ../foo refs/tags/foo 2>/dev/null || true
)
cat >expected <<-EOF 2>/dev/null || true
cat >expected <<-EOF 2>/dev/null || true

true
