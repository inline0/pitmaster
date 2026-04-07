#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "first" > file.txt
git add file.txt
git commit -m "first commit"
git notes add -m "Note on first commit"
echo "second" >> file.txt
git add file.txt
git commit -m "second commit"
git notes add -m "Note on second commit"
