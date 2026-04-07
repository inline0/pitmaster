#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

rm -rf clone.git 2>/dev/null || true
git init clone.git 2>/dev/null || true
rm -rf clone.git 2>/dev/null || true
git init clone.git 2>/dev/null || true
test_commit commit 2>/dev/null || true
git tag -m inner inner HEAD 2>/dev/null || true
git tag -m outer outer inner 2>/dev/null || true
git tag -d inner 2>/dev/null || true

true
