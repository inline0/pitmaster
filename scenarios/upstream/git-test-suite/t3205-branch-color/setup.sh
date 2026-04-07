#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit foo 2>/dev/null || true
git branch -M main 2>/dev/null || true
git update-ref refs/remotes/origin/main HEAD 2>/dev/null || true
git update-ref refs/heads/other HEAD 2>/dev/null || true
git config color.branch.local blue 2>/dev/null || true
git config color.branch.remote yellow 2>/dev/null || true
git config color.branch.current cyan 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
git branch --color -a >actual.raw 2>/dev/null || true

true
