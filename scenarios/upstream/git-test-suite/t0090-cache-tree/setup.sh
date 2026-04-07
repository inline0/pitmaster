#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit foo 2>/dev/null || true
git read-tree HEAD 2>/dev/null || true
echo "I changed this file" >foo 2>/dev/null || true
git add foo 2>/dev/null || true

true
