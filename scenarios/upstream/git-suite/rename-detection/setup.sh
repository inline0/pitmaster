#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "this is the original content of the file that will be renamed" > original.txt
git add original.txt
git commit -m "initial"
git mv original.txt renamed.txt
git commit -m "rename file"
