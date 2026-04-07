#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --global protocol.file.allow always 2>/dev/null || true
test_create_repo child 2>/dev/null || true
test_commit -C child bar 2>/dev/null || true
test_create_repo parent 2>/dev/null || true
test_commit -C child foo 2>/dev/null || true
test_commit -C child merge_strategy 2>/dev/null || true
test_commit -C child rebase_strategy 2>/dev/null || true
test_commit -C super/sub local_stuff 2>/dev/null || true
test_commit -C super/sub local_stuff_2 2>/dev/null || true
test_commit -C child important_upstream_work 2>/dev/null || true

true
