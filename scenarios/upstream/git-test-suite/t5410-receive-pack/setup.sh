#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit base 2>/dev/null || true
git checkout -b public/branch main 2>/dev/null || true
test_commit public 2>/dev/null || true
git checkout -b private/branch main 2>/dev/null || true
test_commit private 2>/dev/null || true
printf "0000" | git receive-pack fork >actual 2>/dev/null || true
printf "0000" | git receive-pack fork >actual 2>/dev/null || true

true
