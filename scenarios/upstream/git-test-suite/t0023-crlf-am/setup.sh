#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config core.autocrlf true 2>/dev/null || true
echo foo >bar 2>/dev/null || true
git add bar 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m initial 2>/dev/null || true

true
