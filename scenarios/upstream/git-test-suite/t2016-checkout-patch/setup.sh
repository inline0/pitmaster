#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir dir 2>/dev/null || true
echo parent > dir/foo 2>/dev/null || true
echo dummy > bar 2>/dev/null || true
git add bar dir/foo 2>/dev/null || true
git commit -m initial 2>/dev/null || true
test_tick 2>/dev/null || true
test_commit second dir/foo head 2>/dev/null || true
test_write_lines n n | git checkout -p 2>/dev/null || true
test_write_lines n y | git checkout -p 2>/dev/null || true

true
