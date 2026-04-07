#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init server 2>/dev/null || true
(
cd server 2>/dev/null || true
test_commit_bulk 4 2>/dev/null || true
)
A=$(git -C server rev-parse HEAD^{tree}) 2>/dev/null || true
B=$(git -C server rev-parse HEAD~1^{tree}) 2>/dev/null || true
C=$(git -C server rev-parse HEAD~2^{tree}) 2>/dev/null || true
(
cd client 2>/dev/null || true
)

true
