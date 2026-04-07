#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config set maintenance.auto false 2>/dev/null || true
test_commit "$i" 2>/dev/null || true
git config pack.allowPackReuse multi 2>/dev/null || true
test_commit C 2>/dev/null || true
cat >in <<-EOF 2>/dev/null || true
test_commit "$i" || return 1 2>/dev/null || true
cat >in <<-EOF 2>/dev/null || true

true
