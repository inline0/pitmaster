#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo oneQ1 | q_to_nul >binary 2>/dev/null || true
git add binary 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo twoQ2 | q_to_nul >binary 2>/dev/null || true
git commit -a -m two 2>/dev/null || true
git checkout -b branch-binary HEAD^ 2>/dev/null || true
echo threeQ3 | q_to_nul >binary 2>/dev/null || true
git commit -a -m three 2>/dev/null || true
echo resolvedQhooray | q_to_nul >binary 2>/dev/null || true
git commit -a -m resolved 2>/dev/null || true

true
