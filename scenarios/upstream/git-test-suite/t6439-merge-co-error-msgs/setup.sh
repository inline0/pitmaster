#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo one >one 2>/dev/null || true
git add one 2>/dev/null || true
git commit -a -m First 2>/dev/null || true
git checkout -b branch 2>/dev/null || true
echo two >two 2>/dev/null || true
echo three >three 2>/dev/null || true
echo four >four 2>/dev/null || true
echo five >five 2>/dev/null || true
git add two three four five 2>/dev/null || true
git commit -m Second 2>/dev/null || true
git checkout main 2>/dev/null || true
echo other >two 2>/dev/null || true
echo other >three 2>/dev/null || true
echo other >four 2>/dev/null || true
echo other >five 2>/dev/null || true
git commit --allow-empty -m empty 2>/dev/null || true
echo "Merge with strategy ort failed." >>expect 2>/dev/null || true
git reset --hard HEAD^ 2>/dev/null || true
git add two 2>/dev/null || true
git add three 2>/dev/null || true
git add four 2>/dev/null || true

true
