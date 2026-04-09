#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

(
cd victim 2>/dev/null || true
)
(
cd victim 2>/dev/null || true
git config receive.denyDeletes true 2>/dev/null || true
git branch extra main 2>/dev/null || true
)
(
cd victim 2>/dev/null || true
git config receive.denyDeletes true 2>/dev/null || true
git branch extra main 2>/dev/null || true
)
(
cd victim 2>/dev/null || true
git config receive.denyDeletes true 2>/dev/null || true
git branch extra main 2>/dev/null || true
)
(
cd victim 2>/dev/null || true
git config receive.denyNonFastforwards true 2>/dev/null || true
)
git branch other-branch HEAD^ 2>/dev/null || true
git init --bare all.git 2>/dev/null || true
mkdir parent 2>/dev/null || true
(
cd parent 2>/dev/null || true
git init && : >file && git add file && git commit -m add 2>/dev/null || true
)
(
cd child && git push --all 2>/dev/null || true
)
(
cd parent 2>/dev/null || true
)
rm -rf parent child 2>/dev/null || true
git init parent 2>/dev/null || true
(
cd parent 2>/dev/null || true
echo "Some text" >file.txt 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "Initial commit" 2>/dev/null || true
echo "Some more text" >>file.txt 2>/dev/null || true
git commit -a -m "Second commit" 2>/dev/null || true
)
cp -R parent child 2>/dev/null || true
(
cd child 2>/dev/null || true
git config gc.autopacklimit 1 2>/dev/null || true
git config gc.autodetach false 2>/dev/null || true
git config maintenance.strategy gc 2>/dev/null || true
git branch test_auto_gc 2>/dev/null || true
)
(
cd parent 2>/dev/null || true
echo "Even more text" >>file.txt 2>/dev/null || true
git commit -a -m "Third commit" 2>/dev/null || true
)
(
cd child 2>/dev/null || true
)
(
cd child 2>/dev/null || true
)
(
cd child 2>/dev/null || true
)
(
cd child 2>/dev/null || true
)

true
