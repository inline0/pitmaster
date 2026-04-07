#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_create_repo sub 2>/dev/null || true
touch sub/file 2>/dev/null || true
test_create_repo a_repo 2>/dev/null || true
git add a_file 2>/dev/null || true
git commit -m "add a file" 2>/dev/null || true
git branch test 2>/dev/null || true
git checkout test 2>/dev/null || true
mkdir sub 2>/dev/null || true
git add sub/content 2>/dev/null || true
git commit -m "add a regular folder with name sub" 2>/dev/null || true
echo "123" >a_file 2>/dev/null || true
git add a_file 2>/dev/null || true
git commit -m "modify a file" 2>/dev/null || true
git checkout main 2>/dev/null || true
git submodule add ../sub sub 2>/dev/null || true
git submodule update sub 2>/dev/null || true
git commit -m "add a submodule info folder with name sub" 2>/dev/null || true
git cherry-pick test 2>/dev/null || true

true
