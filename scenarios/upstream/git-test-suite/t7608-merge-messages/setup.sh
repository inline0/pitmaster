#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit main-1 2>/dev/null || true
git checkout -b local-branch 2>/dev/null || true
test_commit branch-1 2>/dev/null || true
git checkout main 2>/dev/null || true
test_commit main-2 2>/dev/null || true
git merge local-branch 2>/dev/null || true
git checkout -b octopus-a main 2>/dev/null || true
test_commit octopus-1 2>/dev/null || true
git checkout -b octopus-b main 2>/dev/null || true
test_commit octopus-2 2>/dev/null || true
git checkout main 2>/dev/null || true
git merge octopus-a octopus-b 2>/dev/null || true
git checkout -b tag-branch main 2>/dev/null || true
test_commit tag-1 2>/dev/null || true
git checkout main 2>/dev/null || true
test_commit main-3 2>/dev/null || true
git merge tag-1 2>/dev/null || true

true
