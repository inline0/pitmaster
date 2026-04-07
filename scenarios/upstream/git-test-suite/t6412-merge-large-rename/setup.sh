#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

touch file 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git tag initial 2>/dev/null || true
git config diff.renamelimit 4 2>/dev/null || true
git config merge.renamelimit 5 2>/dev/null || true

true
