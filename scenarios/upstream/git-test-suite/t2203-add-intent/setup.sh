#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit 1 2>/dev/null || true
git rm 1.t 2>/dev/null || true
echo hello >1.t 2>/dev/null || true
echo hello >file 2>/dev/null || true
echo hello >elif 2>/dev/null || true
git add -N file 2>/dev/null || true
git add elif 2>/dev/null || true
git add -N 1.t 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
