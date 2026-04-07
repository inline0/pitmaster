#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_write_lines "TILRAUN: Halló Heimur!" >file 2>/dev/null || true
git add file 2>/dev/null || true
test_write_lines "TILRAUN: Hallóó Heimur!" >file2 2>/dev/null || true
git add file2 2>/dev/null || true
echo file >expected 2>/dev/null || true
echo file2 >>expected 2>/dev/null || true
test_write_lines "TILRAUN: Halló Heimur [abc]!" >file3 2>/dev/null || true
git add file3 2>/dev/null || true
git commit -m first 2>/dev/null || true
echo first >expected 2>/dev/null || true

true
