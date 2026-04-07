#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
for i in $(seq -w 1 100); do echo "file $i" > "file-$i.txt"; done
mkdir -p deep/nested/path
echo "deep" > deep/nested/path/deep.txt
git add .
git commit -m "100 files + deep nesting"
