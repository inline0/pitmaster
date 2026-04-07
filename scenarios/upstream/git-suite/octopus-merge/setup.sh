#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "base" > file.txt
git add file.txt
git commit -m "base"
git checkout -b a
echo "a" > a.txt
git add a.txt
git commit -m "branch a"
git checkout main
git checkout -b b
echo "b" > b.txt
git add b.txt
git commit -m "branch b"
git checkout main
git checkout -b c
echo "c" > c.txt
git add c.txt
git commit -m "branch c"
git checkout main
git merge a b c -m "octopus merge" || true
