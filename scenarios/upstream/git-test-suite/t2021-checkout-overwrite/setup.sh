#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir -p a/b/c 2>/dev/null || true
git add -A 2>/dev/null || true
git commit -m base 2>/dev/null || true
git tag start 2>/dev/null || true
git checkout -b file 2>/dev/null || true
git add -A 2>/dev/null || true
git commit -m "dir to file" 2>/dev/null || true
git rm --cached a/b 2>/dev/null || true
git commit -m "un-track the file" 2>/dev/null || true

true
