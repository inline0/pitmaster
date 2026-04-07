#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit unrelated 2>/dev/null || true
test_commit no-conflict 2>/dev/null || true
test_commit conflict-patch file patch 2>/dev/null || true
git reset --hard unrelated 2>/dev/null || true
test_commit conflict-main file main base 2>/dev/null || true
echo resolved >file 2>/dev/null || true
git add -u 2>/dev/null || true
git reset --hard base 2>/dev/null || true
test_write_lines y n | git am -i mbox 2>/dev/null || true
echo no-conflict >expect 2>/dev/null || true

true
