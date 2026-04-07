#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --global protocol.file.allow always 2>/dev/null || true
git init sm 2>/dev/null || true
test_commit -C sm "add file" file file-content file-tag 2>/dev/null || true
git submodule add ./sm my-subm 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "add submodule" 2>/dev/null || true
test_commit -C sm "add file2" file2 file2-content file2-tag 2>/dev/null || true
git submodule update --remote 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "update submodule" my-subm 2>/dev/null || true
git submodule summary HEAD^ >actual 2>/dev/null || true
cat >expected <<-EOF 2>/dev/null || true

true
