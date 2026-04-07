#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo content >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m file 2>/dev/null || true
echo modification >file 2>/dev/null || true
echo file >.gitignore 2>/dev/null || true
echo content >other-file 2>/dev/null || true
git add other-file 2>/dev/null || true
echo file >expect 2>/dev/null || true

true
