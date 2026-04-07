#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo Hello >A 2>/dev/null || true
git update-index --add A 2>/dev/null || true
git commit -m "Initial commit." 2>/dev/null || true
git branch -M main 2>/dev/null || true
echo World >>A 2>/dev/null || true
git update-index --add A 2>/dev/null || true
git commit -m "Second commit." 2>/dev/null || true
HEAD=$(git rev-parse --verify HEAD) 2>/dev/null || true
mkdir broken 2>/dev/null || true
git init -b main 2>/dev/null || true

true
