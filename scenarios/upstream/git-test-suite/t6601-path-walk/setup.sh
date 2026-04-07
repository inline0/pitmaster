#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git checkout -b base 2>/dev/null || true
mkdir child 2>/dev/null || true
echo file >child/file 2>/dev/null || true
git add child 2>/dev/null || true
git commit -m "will abandon" 2>/dev/null || true
git tag -a -m "tree" tree-tag HEAD^{tree} 2>/dev/null || true
echo file2 >file2 2>/dev/null || true
git add file2 2>/dev/null || true
git commit --amend -m "will abandon" 2>/dev/null || true
git tag tree-tag2 HEAD^{tree} 2>/dev/null || true
echo blob >file 2>/dev/null || true
git tag -a -m "blob" blob-tag "$blob_oid" 2>/dev/null || true
echo blob2 >file2 2>/dev/null || true
git tag blob-tag2 "$blob2_oid" 2>/dev/null || true
mkdir left 2>/dev/null || true
mkdir right 2>/dev/null || true
echo a >a 2>/dev/null || true
echo b >left/b 2>/dev/null || true
echo c >right/c 2>/dev/null || true
git add . 2>/dev/null || true
git commit --amend -m "first" 2>/dev/null || true
git tag -m "first" first HEAD 2>/dev/null || true
echo d >right/d 2>/dev/null || true
git add right 2>/dev/null || true
git commit -m "second" 2>/dev/null || true
git tag -a -m "second (under)" second.1 HEAD 2>/dev/null || true
git tag -a -m "second (top)" second.2 second.1 2>/dev/null || true
mkdir a 2>/dev/null || true
echo a >a/a 2>/dev/null || true
echo bb >left/b 2>/dev/null || true
git add a left 2>/dev/null || true
git commit -m "third" 2>/dev/null || true
git tag -a -m "third" third 2>/dev/null || true
git checkout -b topic HEAD~1 2>/dev/null || true
echo cc >right/c 2>/dev/null || true
git commit -a -m "topic" 2>/dev/null || true
git tag -a -m "fourth" fourth 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
echo bogus >left/c 2>/dev/null || true
git add left 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
