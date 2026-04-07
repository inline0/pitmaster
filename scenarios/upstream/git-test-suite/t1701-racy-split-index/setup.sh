#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config splitIndex.maxPercentChange 100 2>/dev/null || true
echo "cached content" >racy-file 2>/dev/null || true
git add racy-file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo something >other-file 2>/dev/null || true
echo "+cached content" >expect 2>/dev/null || true

true
