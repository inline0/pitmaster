#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_tick 2>/dev/null || true
git commit --allow-empty -m fifth 2>/dev/null || true
test_tick 2>/dev/null || true
git tag -a -m "tip five" five 2>/dev/null || true
(
cd dst 2>/dev/null || true
)
test_tick 2>/dev/null || true
git commit --allow-empty -m sixth 2>/dev/null || true
test_tick 2>/dev/null || true
git tag -a -m "tip two" two three^1 2>/dev/null || true
(
cd dst 2>/dev/null || true
)
test_tick 2>/dev/null || true
git tag -a -m "tip one " one two^1 2>/dev/null || true
(
cd dst 2>/dev/null || true
)
mkdir remote 2>/dev/null || true
git init --bare remote/one.git 2>/dev/null || true
mkdir remote/host 2>/dev/null || true
git init --bare remote/host/two.git 2>/dev/null || true
rm -rf dst 2>/dev/null || true

true
