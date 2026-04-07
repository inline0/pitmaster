#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --global protocol.file.allow always 2>/dev/null || true
git init sub 2>/dev/null || true
test_commit --no-tag -C sub "initial commit in sub" file.txt "sub content" 2>/dev/null || true
git init super 2>/dev/null || true
mkdir subdir 2>/dev/null || true
test_commit --no-tag -C subdir "initial commit in super" main.txt "super content" 2>/dev/null || true
git submodule add ../sub subpath 2>/dev/null || true
git commit -m "add submodule 'sub' at subpath" 2>/dev/null || true
git submodule update --init 2>/dev/null || true
echo "origin" >expect 2>/dev/null || true
git submodule--helper get-default-remote subpath >actual 2>/dev/null || true

true
