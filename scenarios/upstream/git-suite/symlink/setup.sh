#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "target content" > target.txt
ln -s target.txt link.txt
git add target.txt link.txt
git commit -m "with symlink"
