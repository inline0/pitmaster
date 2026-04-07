#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit branch file 2>/dev/null || true
git branch ambiguity 2>/dev/null || true
git branch vagueness 2>/dev/null || true
test_commit tag file 2>/dev/null || true
git tag ambiguity 2>/dev/null || true
git tag vagueness HEAD:file 2>/dev/null || true
test_commit other file 2>/dev/null || true
git checkout ambiguity 2>stderr 2>/dev/null || true

true
