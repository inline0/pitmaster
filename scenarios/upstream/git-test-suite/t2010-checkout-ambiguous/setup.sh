#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo hello >world 2>/dev/null || true
echo hello >all 2>/dev/null || true
git add all world 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git branch world 2>/dev/null || true
git checkout world -- 2>/dev/null || true

true
