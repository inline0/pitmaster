#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init with-commit 2>/dev/null || true
test_commit -C with-commit the-commit 2>/dev/null || true
git init with-commit-graph 2>/dev/null || true
test_commit -C with-commit-graph something 2>/dev/null || true
git init --bare without-commit 2>/dev/null || true
test_commit -C with-commit any-commit 2>/dev/null || true

true
