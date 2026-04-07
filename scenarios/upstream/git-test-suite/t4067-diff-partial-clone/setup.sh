#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_create_repo server 2>/dev/null || true
echo a >server/a 2>/dev/null || true
echo b >server/b 2>/dev/null || true
test_create_repo server 2>/dev/null || true
echo a >server/a 2>/dev/null || true
echo b >server/b 2>/dev/null || true
echo c >server/c 2>/dev/null || true
echo d >server/d 2>/dev/null || true
test_create_repo server 2>/dev/null || true
echo a >server/a 2>/dev/null || true
echo b >server/b 2>/dev/null || true
echo another-a >server/a 2>/dev/null || true
echo a | git hash-object --stdin >hash-old-a 2>/dev/null || true
echo another-a | git hash-object --stdin >hash-new-a 2>/dev/null || true
echo b | git hash-object --stdin >hash-b 2>/dev/null || true
test_create_repo sub 2>/dev/null || true
test_commit -C sub first 2>/dev/null || true
test_create_repo server 2>/dev/null || true
echo a >server/a 2>/dev/null || true
test_commit -C server/sub second 2>/dev/null || true
echo another-a >server/a 2>/dev/null || true
echo a | git hash-object --stdin >hash-old-a 2>/dev/null || true
echo another-a | git hash-object --stdin >hash-new-a 2>/dev/null || true
test_create_repo server 2>/dev/null || true
echo a >server/a 2>/dev/null || true
printf "b\nb\nb\nb\nb\n" >server/b 2>/dev/null || true
rm server/b 2>/dev/null || true
printf "b\nb\nb\nb\nbX\n" >server/c 2>/dev/null || true

true
