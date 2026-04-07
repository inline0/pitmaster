#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo 1 >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m 1 2>/dev/null || true
echo 2 >file 2>/dev/null || true
git commit -a -m 2 2>/dev/null || true

true
