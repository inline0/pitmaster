#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir another 2>/dev/null || true
git init 2>/dev/null || true
echo ../../../.git/objects >.git/objects/info/alternates 2>/dev/null || true
test_commit C fileC one 2>/dev/null || true
git init --bare hash-mismatch 2>/dev/null || true
mv objects/$old objects/$new 2>/dev/null || true
git update-index --add --cacheinfo 100644 $oid foo 2>/dev/null || true
git update-ref refs/heads/bogus $cmt 2>/dev/null || true

true
