#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_tick 2>/dev/null || true
git commit --allow-empty -m preimage 2>/dev/null || true
git tag preimage 2>/dev/null || true
git checkout -f preimage^0 2>/dev/null || true
git read-tree -u --reset HEAD 2>/dev/null || true
git update-index --refresh 2>/dev/null || true
echo postimage >expected 2>/dev/null || true
echo postimage >expected 2>/dev/null || true

true
