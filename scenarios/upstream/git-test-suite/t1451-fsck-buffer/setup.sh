#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git commit --allow-empty -m foo 2>/dev/null || true
printf "100644 foo\0\1\1\1\1" >input 2>/dev/null || true

true
