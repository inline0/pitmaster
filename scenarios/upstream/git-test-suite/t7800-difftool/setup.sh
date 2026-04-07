#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir -p not/repo 2>/dev/null || true
echo main >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m "added file" 2>/dev/null || true
git checkout -b branch main 2>/dev/null || true
echo branch >file 2>/dev/null || true
git commit -a -m "branch changed file" 2>/dev/null || true
git checkout main 2>/dev/null || true
echo main >expect 2>/dev/null || true
echo branch >expect 2>/dev/null || true

true
