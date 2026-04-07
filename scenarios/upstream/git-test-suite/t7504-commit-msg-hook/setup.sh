#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo "foo" > file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m "first" 2>/dev/null || true
echo "more foo" >> file 2>/dev/null || true
git add file 2>/dev/null || true
echo "more foo" > FAKE_MSG 2>/dev/null || true
echo "bar" > file 2>/dev/null || true
git add file 2>/dev/null || true
git commit --no-verify -m "bar" 2>/dev/null || true

true
