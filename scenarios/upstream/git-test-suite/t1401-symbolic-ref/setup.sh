#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git symbolic-ref HEAD refs/heads/foo 2>/dev/null || true
test_commit file 2>/dev/null || true
git symbolic-ref HEAD refs/heads/read-write-roundtrip 2>/dev/null || true
echo refs/heads/read-write-roundtrip >expect 2>/dev/null || true
git symbolic-ref HEAD >actual 2>/dev/null || true
git symbolic-ref NOTHEAD refs/heads/foo 2>/dev/null || true
git symbolic-ref -d NOTHEAD 2>/dev/null || true
git symbolic-ref NOTHEAD refs/heads/missing 2>/dev/null || true
git symbolic-ref -d NOTHEAD 2>/dev/null || true
echo "fatal: Cannot delete FOO, not a symbolic ref" >expect 2>/dev/null || true
echo "fatal: Cannot delete refs/heads/foo, not a symbolic ref" >expect 2>/dev/null || true
echo >&2 "long refs not supported" 2>/dev/null || true
git symbolic-ref HEAD $long_ref 2>/dev/null || true
echo $long_ref >expect 2>/dev/null || true
git symbolic-ref HEAD >actual 2>/dev/null || true
echo $commit >expect 2>/dev/null || true
git checkout -b log1 2>/dev/null || true
test_commit one 2>/dev/null || true
git checkout -b log2 2>/dev/null || true
test_commit two 2>/dev/null || true
git checkout --orphan orphan 2>/dev/null || true
git symbolic-ref -m create HEAD refs/heads/log1 2>/dev/null || true
git symbolic-ref -m update HEAD refs/heads/log2 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true

true
