#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

rm -rf child 2>/dev/null || true
echo bar >expect 2>/dev/null || true
rm -rf child 2>/dev/null || true
test_write_lines bar baz >expect 2>/dev/null || true
rm -rf child 2>/dev/null || true
printf "%s\n" "" hi >expect 2>/dev/null || true
rm -rf child 2>/dev/null || true
echo true >expect 2>/dev/null || true
echo content >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m one 2>/dev/null || true
rm -rf child 2>/dev/null || true
printf "content\\r\\n" >expect 2>/dev/null || true

true
