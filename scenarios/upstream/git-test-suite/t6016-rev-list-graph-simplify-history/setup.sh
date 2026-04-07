#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit A1 foo.txt 2>/dev/null || true
test_commit A2 bar.txt 2>/dev/null || true
test_commit A3 bar.txt 2>/dev/null || true
git branch -m main A 2>/dev/null || true
git checkout -b B A1 2>/dev/null || true
test_commit B1 foo.txt 2>/dev/null || true
test_commit B2 abc.txt 2>/dev/null || true
git checkout -b C A2 2>/dev/null || true
test_commit C1 xyz.txt 2>/dev/null || true
test_commit C2 xyz.txt 2>/dev/null || true
git checkout A 2>/dev/null || true
git merge B C -m A4 2>/dev/null || true
git tag A4 2>/dev/null || true
test_commit A5 bar.txt 2>/dev/null || true
git checkout C 2>/dev/null || true
test_commit C3 foo.txt 2>/dev/null || true
test_commit C4 bar.txt 2>/dev/null || true
git checkout A 2>/dev/null || true
git merge -s ours C -m A6 2>/dev/null || true
git tag A6 2>/dev/null || true
test_commit A7 bar.txt 2>/dev/null || true
git tag -d A4 2>/dev/null || true

true
