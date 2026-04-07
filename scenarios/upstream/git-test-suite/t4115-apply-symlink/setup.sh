#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git checkout side 2>/dev/null || true
git checkout -f side 2>/dev/null || true
ln -s .git symlink 2>/dev/null || true
git add symlink 2>/dev/null || true
git commit -m "add symlink" 2>/dev/null || true

true
