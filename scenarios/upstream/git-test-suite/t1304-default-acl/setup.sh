#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

touch should-have-readable-acl 2>/dev/null || true
touch file.txt 2>/dev/null || true
git add file.txt 2>/dev/null || true
git commit -m "init" 2>/dev/null || true

true
