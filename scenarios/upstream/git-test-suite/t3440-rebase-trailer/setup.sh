#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git commit --allow-empty -m "Initial empty commit" 2>/dev/null || true
test_commit first file a 2>/dev/null || true
test_commit second file 2>/dev/null || true
git checkout -b conflict-branch first 2>/dev/null || true
test_commit file-2 file-2 2>/dev/null || true
test_commit conflict file 2>/dev/null || true
test_commit third file 2>/dev/null || true
git checkout main 2>/dev/null || true
git checkout -B apply-backend third 2>/dev/null || true
git rebase --apply --trailer "$REVIEWED_BY_TRAILER" HEAD^ 2>err 2>/dev/null || true
git checkout -B empty-trailer third 2>/dev/null || true

true
