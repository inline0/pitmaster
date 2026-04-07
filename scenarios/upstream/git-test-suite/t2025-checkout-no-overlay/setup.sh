#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git commit --allow-empty -m "initial" 2>/dev/null || true
mkdir dir 2>/dev/null || true
git add file dir/file1 2>/dev/null || true
git checkout --no-overlay HEAD -- file 2>/dev/null || true
git checkout --no-overlay HEAD -- dir/file1 2>/dev/null || true

true
