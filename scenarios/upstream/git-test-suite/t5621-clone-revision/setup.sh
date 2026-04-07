#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit --no-tag "initial commit" README "Hello" 2>/dev/null || true
test_commit --annotate "second commit" README "Hello world" v1.0 2>/dev/null || true
test_commit --no-tag "third commit" README "Hello world!" 2>/dev/null || true
git switch -c feature v1.0 2>/dev/null || true
test_commit --no-tag "feature commit" README "Hello world!" 2>/dev/null || true
git switch main 2>/dev/null || true

true
