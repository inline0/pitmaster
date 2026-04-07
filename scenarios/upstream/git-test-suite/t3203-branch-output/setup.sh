#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo content >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m one 2>/dev/null || true
git branch -M main 2>/dev/null || true
echo content >>file 2>/dev/null || true
git commit -a -m two 2>/dev/null || true
git branch branch-one 2>/dev/null || true
git branch branch-two HEAD^ 2>/dev/null || true
git update-ref refs/remotes/origin/branch-one branch-one 2>/dev/null || true
git update-ref refs/remotes/origin/branch-two branch-two 2>/dev/null || true
git symbolic-ref refs/remotes/origin/HEAD refs/remotes/origin/branch-one 2>/dev/null || true
git branch >actual 2>/dev/null || true
git branch --list >actual 2>/dev/null || true

true
