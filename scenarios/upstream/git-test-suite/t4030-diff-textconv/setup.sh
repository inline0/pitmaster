#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit --printf one file "\\0\\n" 2>/dev/null || true
test_commit --printf --append two file "\\01\\n" 2>/dev/null || true

true
