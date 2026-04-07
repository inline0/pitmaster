#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --global advice.statusuoption false 2>/dev/null || true
test_commit init main.txt init 2>/dev/null || true
git branch conflicts 2>/dev/null || true
test_commit on_main main.txt on_main 2>/dev/null || true
git checkout conflicts 2>/dev/null || true
test_commit on_conflicts main.txt on_conflicts 2>/dev/null || true
cat >expected <<\EOF 2>/dev/null || true
git reset --hard conflicts 2>/dev/null || true
echo one >main.txt 2>/dev/null || true
git add main.txt 2>/dev/null || true
cat >expected <<\EOF 2>/dev/null || true

true
