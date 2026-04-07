#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit one 2>/dev/null || true
git submodule add "$PWD" sub 2>/dev/null || true
git commit -m "add submodule" 2>/dev/null || true
echo ":12345 $old" >from 2>/dev/null || true
echo ":12345 $new" >to 2>/dev/null || true

true
