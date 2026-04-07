#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir nonrepo-no-files/ 2>/dev/null || true
mkdir nonrepo-untracked-file 2>/dev/null || true
git init repo-no-commit-no-files 2>/dev/null || true
git init repo-no-commit-untracked-file 2>/dev/null || true
git init repo-with-commit-no-files 2>/dev/null || true
git init repo-with-commit-untracked-file 2>/dev/null || true
test_commit -C repo-with-commit-untracked-file msg 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
