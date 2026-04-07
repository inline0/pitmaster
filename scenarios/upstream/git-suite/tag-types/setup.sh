#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "content" > file.txt
git add file.txt
git commit -m "initial"
git tag lightweight
git tag -a annotated -m "annotated tag message"
git tag -a nested-target -m "target for nested"
echo "more" >> file.txt
git add file.txt
git commit -m "second"
git tag -a v2.0 -m "version 2"
