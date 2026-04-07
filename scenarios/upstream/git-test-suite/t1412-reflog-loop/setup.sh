#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit one file content 2>/dev/null || true
test_commit --append two file content 2>/dev/null || true
git checkout -b topic 2>/dev/null || true
git reset one 2>/dev/null || true
git reset two 2>/dev/null || true
git reset one 2>/dev/null || true
git reset two 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true

true
