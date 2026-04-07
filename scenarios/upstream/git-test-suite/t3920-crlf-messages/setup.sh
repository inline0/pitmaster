#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit initial 2>/dev/null || true
printf "  " >>expect 2>/dev/null || true
cat .crlf-subject-${branch}.txt >>expect 2>/dev/null || true
printf "\n" >>expect || return 1 2>/dev/null || true
git branch -v >tmp 2>/dev/null || true
cat .crlf-subject-${branch}.txt >expect 2>/dev/null || true
printf "\n" >>expect 2>/dev/null || true

true
