#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo a >file 2>/dev/null || true
git add file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo b >>file 2>/dev/null || true
echo c >>file 2>/dev/null || true
echo d >>file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -a -m two 2>/dev/null || true

true
