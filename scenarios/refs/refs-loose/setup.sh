#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

echo "initial" > file.txt
git add file.txt
git commit -m "Initial commit"

git branch feature
git tag v1.0
