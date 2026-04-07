#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir -p sub1/sub2 2>/dev/null || true
echo top >top 2>/dev/null || true
echo file1 >sub1/file1 2>/dev/null || true
echo file2 >sub1/sub2/file2 2>/dev/null || true
git add top sub1/file1 sub1/sub2/file2 2>/dev/null || true
git commit -m commit 2>/dev/null || true
cat <<-\EOF >expected 2>/dev/null || true
cat <<-\EOF >expected 2>/dev/null || true

true
