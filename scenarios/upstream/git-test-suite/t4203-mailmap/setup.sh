#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit initial one one 2>/dev/null || true
test_commit --author "nick1 <bugs@company.xx>" --append second one two 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
