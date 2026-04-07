#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init template 2>/dev/null || true
test_commit -C template 1 2>/dev/null || true
test_commit -C template 2 2>/dev/null || true
test_commit -C template 3 2>/dev/null || true
mv server/objects/pack/pack-* . 2>/dev/null || true

true
