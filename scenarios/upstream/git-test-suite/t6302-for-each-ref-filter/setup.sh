#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit one 2>/dev/null || true
git branch -M main 2>/dev/null || true
test_commit two 2>/dev/null || true
test_commit three 2>/dev/null || true
git checkout -b side 2>/dev/null || true
test_commit four 2>/dev/null || true
git tag -m "An annotated tag" annotated-tag 2>/dev/null || true
git tag -m "Annotated doubly" doubly-annotated-tag annotated-tag 2>/dev/null || true
git tag $sign -m "A signed tag" signed-tag 2>/dev/null || true
git tag $sign -m "Signed doubly" doubly-signed-tag signed-tag 2>/dev/null || true
git checkout main 2>/dev/null || true
git update-ref refs/odd/spot main 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
git update-ref ORIG_HEAD main 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit initial 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
