#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo one >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo two >file 2>/dev/null || true
git commit -a -m two 2>/dev/null || true
git tag two 2>/dev/null || true
echo three >file 2>/dev/null || true
git commit -a -m three 2>/dev/null || true
git checkout -b side 2>/dev/null || true
echo four >file 2>/dev/null || true
git commit -a -m four 2>/dev/null || true
git checkout main 2>/dev/null || true
git tag five 2>/dev/null || true
git checkout side 2>/dev/null || true
git checkout main 2>/dev/null || true
git checkout two^ 2>/dev/null || true
git checkout side 2>/dev/null || true
echo five >file 2>/dev/null || true
git commit -a -m five 2>/dev/null || true
git checkout main 2>/dev/null || true
echo six >file 2>/dev/null || true
git commit -a -m six 2>/dev/null || true
git tag -d two && git tag two 2>/dev/null || true

true
