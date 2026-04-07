#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo content >file 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m one 2>/dev/null || true
git update-ref refs/for/refs/heads/main HEAD 2>/dev/null || true
echo content >>file 2>/dev/null || true
git commit -a -m two 2>/dev/null || true
echo content >>file 2>/dev/null || true
git commit -a -m three 2>/dev/null || true
git checkout HEAD^ 2>/dev/null || true
echo three >expect 2>/dev/null || true
git init long 2>/dev/null || true
test_commit long 2>/dev/null || true
test_commit main 2>/dev/null || true
echo >&2 "long refs not supported" 2>/dev/null || true

true
