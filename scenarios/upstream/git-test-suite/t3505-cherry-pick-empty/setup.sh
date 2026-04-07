#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git checkout main 2>/dev/null || true
git checkout main 2>/dev/null || true
git cherry-pick empty-message-branch 2>/dev/null || true
git checkout -f main 2>/dev/null || true
git cherry-pick --allow-empty-message empty-message-branch 2>/dev/null || true
git checkout main 2>/dev/null || true
echo fourth >>file2 2>/dev/null || true
git add file2 2>/dev/null || true
git commit -m "fourth" 2>/dev/null || true

true
