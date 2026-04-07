#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir refdir 2>/dev/null || true
git config get extensions.refstorage >actual 2>/dev/null || true
echo $BACKEND >expect 2>/dev/null || true
test_commit 1 2>/dev/null || true
test_commit 2 2>/dev/null || true
test_commit 3 2>/dev/null || true
mkdir refdir 2>/dev/null || true
git init source 2>/dev/null || true
test_commit -C source 1 2>/dev/null || true
test_commit -C source 2 2>/dev/null || true
test_commit -C source 3 2>/dev/null || true
echo $BACKEND >expect 2>/dev/null || true

true
