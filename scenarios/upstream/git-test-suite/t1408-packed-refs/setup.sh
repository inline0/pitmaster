#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo "v1.0" >expect 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit --no-tag A 2>/dev/null || true
git update-ref refs/heads/ HEAD 2>/dev/null || true
git update-ref refs/heads/z HEAD 2>/dev/null || true
git pack-refs --all 2>/dev/null || true
printf "%s commit\trefs/heads/z\n" $(git rev-parse HEAD) >expect 2>/dev/null || true

true
