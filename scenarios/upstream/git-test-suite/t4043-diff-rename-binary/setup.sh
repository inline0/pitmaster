#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init 2>/dev/null || true
echo foo > foo 2>/dev/null || true
echo "barQ" | q_to_nul > bar 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "Initial commit" 2>/dev/null || true
mkdir sub 2>/dev/null || true
git mv bar foo sub/ 2>/dev/null || true
git commit -m "Moved to sub/" 2>/dev/null || true

true
