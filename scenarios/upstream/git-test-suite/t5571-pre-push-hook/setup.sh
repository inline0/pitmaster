#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

cat >actual 2>/dev/null || true
git config push.default upstream 2>/dev/null || true
git init --bare repo1 2>/dev/null || true
test_commit one 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
cat >actual 2>/dev/null || true
test_commit two 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
