#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit one 2>/dev/null || true
echo refs/heads/main >expected 2>/dev/null || true
git symbolic-ref FOO >actual 2>/dev/null || true
git tag -a -m new-tag new-tag HEAD 2>/dev/null || true

true
