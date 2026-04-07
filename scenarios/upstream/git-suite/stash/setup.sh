#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "original" > file.txt
git add file.txt
git commit -m "initial"
echo "modified" > file.txt
git stash push -m "test stash"
echo "another" > other.txt
git add other.txt
git stash push -m "second stash"
