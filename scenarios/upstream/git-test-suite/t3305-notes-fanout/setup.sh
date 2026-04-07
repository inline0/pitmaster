#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git checkout -b nondeterminism 2>/dev/null || true
test_commit A 2>/dev/null || true
git checkout --orphan with_notes; 2>/dev/null || true
test_tick 2>/dev/null || true
echo "file for commit #$i" > file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -q -m "commit #$i" 2>/dev/null || true
git notes add -m "note #$i" || return 1 2>/dev/null || true

true
