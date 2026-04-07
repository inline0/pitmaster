#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo c0 > c0.c 2>/dev/null || true
git add c0.c 2>/dev/null || true
git commit -m c0 2>/dev/null || true
git tag c0 2>/dev/null || true
echo c1 > c1.c 2>/dev/null || true
git add c1.c 2>/dev/null || true
git commit -m c1 2>/dev/null || true
git tag c1 2>/dev/null || true
git reset --hard c0 2>/dev/null || true
echo c2 > c2.c 2>/dev/null || true
git add c2.c 2>/dev/null || true
git commit -m c2 2>/dev/null || true
git tag c2 2>/dev/null || true
git reset --hard c0 2>/dev/null || true
echo c3 > c2.c 2>/dev/null || true
git add c2.c 2>/dev/null || true
git commit -m c3 2>/dev/null || true
git tag c3 2>/dev/null || true
git reset --hard c2 2>/dev/null || true

true
