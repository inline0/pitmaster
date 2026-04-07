#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "hello" > file.txt
git add file.txt
git commit -m "initial"
for i in $(seq 1 20); do echo "line $i" >> file.txt; git add file.txt; git commit -m "commit $i"; done
git gc
