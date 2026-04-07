#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "base" > file.txt
git add file.txt
git commit -m "base"
git checkout -b left
echo "left change" >> file.txt
git add file.txt
git commit -m "left"
git checkout main
git checkout -b right
echo "right change" >> other.txt
git add other.txt
git commit -m "right"
git checkout main
git merge left --no-edit
git merge right --no-edit
