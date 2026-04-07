#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

touch foo bar 2>/dev/null || true
git update-index --add foo bar 2>/dev/null || true
git commit -m "add foo bar" 2>/dev/null || true

true
