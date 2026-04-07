#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo hello >world 2>/dev/null || true
git update-index --add world 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git branch side 2>/dev/null || true
echo goodbye >world 2>/dev/null || true
git update-index --add world 2>/dev/null || true
git commit -m second 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout main 2>/dev/null || true
git checkout side 2>/dev/null || true
git checkout main 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout main world 2>/dev/null || true
git checkout side world 2>/dev/null || true
git checkout main world 2>/dev/null || true

true
