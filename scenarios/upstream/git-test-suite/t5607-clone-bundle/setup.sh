#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit initial 2>/dev/null || true
test_tick 2>/dev/null || true
git tag -m tag tag 2>/dev/null || true
test_commit second 2>/dev/null || true
test_commit third 2>/dev/null || true
git tag -d initial 2>/dev/null || true
git tag -d second 2>/dev/null || true
git tag -d third 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
