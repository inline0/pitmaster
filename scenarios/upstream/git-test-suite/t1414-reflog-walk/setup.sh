#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit one 2>/dev/null || true
test_commit two 2>/dev/null || true
git checkout -b side HEAD^ 2>/dev/null || true
test_commit three 2>/dev/null || true
git merge --no-commit main 2>/dev/null || true
echo evil-merge-content >>one.t 2>/dev/null || true
test_tick 2>/dev/null || true
git commit --no-edit -a 2>/dev/null || true
cat >expect.all <<-EOF 2>/dev/null || true

true
