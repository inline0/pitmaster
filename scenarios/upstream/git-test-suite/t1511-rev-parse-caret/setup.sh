#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo blob >a-blob 2>/dev/null || true
git tag -a -m blob blob-tag $(git hash-object -w a-blob) 2>/dev/null || true
mkdir a-tree 2>/dev/null || true
echo moreblobs >a-tree/another-blob 2>/dev/null || true
git add . 2>/dev/null || true
git tag -a -m tree tree-tag "$TREE_SHA1" 2>/dev/null || true
git commit -m Initial 2>/dev/null || true
git tag -a -m commit commit-tag 2>/dev/null || true
git branch ref 2>/dev/null || true
git checkout main 2>/dev/null || true
echo modified >>a-blob 2>/dev/null || true
git add -u 2>/dev/null || true
git commit -m Modified 2>/dev/null || true
git branch modref 2>/dev/null || true
echo changed! >>a-blob 2>/dev/null || true
git add -u 2>/dev/null || true
git commit -m !Exp 2>/dev/null || true
git branch expref 2>/dev/null || true
echo changed >>a-blob 2>/dev/null || true
git add -u 2>/dev/null || true
git commit -m Changed 2>/dev/null || true
echo changed-again >>a-blob 2>/dev/null || true
git add -u 2>/dev/null || true
git commit -m Changed-again 2>/dev/null || true

true
