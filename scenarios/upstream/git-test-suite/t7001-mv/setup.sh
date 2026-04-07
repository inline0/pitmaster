#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo test >bar 2>/dev/null || true
git add bar 2>/dev/null || true
git commit -m test 2>/dev/null || true
echo foo >foo 2>/dev/null || true
git add foo 2>/dev/null || true
git mv -f foo bar 2>/dev/null || true
git reset --merge HEAD 2>/dev/null || true
mkdir path0 path1 2>/dev/null || true
git add path0/COPYING 2>/dev/null || true
git commit -m add -a 2>/dev/null || true

true
