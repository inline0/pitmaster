#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init repo 2>/dev/null || true
test_commit first 2>/dev/null || true
test_commit second 2>/dev/null || true
test_commit third 2>/dev/null || true
git symbolic-ref HEAD >expect 2>/dev/null || true
git symbolic-ref HEAD >actual 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit first 2>/dev/null || true
test_commit second 2>/dev/null || true
test_commit third 2>/dev/null || true
git symbolic-ref HEAD >expect 2>/dev/null || true
git symbolic-ref HEAD >actual 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit first 2>/dev/null || true
test_commit second 2>/dev/null || true
test_commit third_on_main 2>/dev/null || true
git checkout --detach HEAD^ 2>/dev/null || true
test_commit third_on_head 2>/dev/null || true

true
