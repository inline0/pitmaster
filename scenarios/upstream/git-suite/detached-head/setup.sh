#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "first" > file.txt
git add file.txt
git commit -m "first"
echo "second" >> file.txt
git add file.txt
git commit -m "second"
git checkout HEAD~1
