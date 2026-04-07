#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

echo "first" > file.txt
git add file.txt
git commit -m "First commit"

echo "second" >> file.txt
git add file.txt
git commit -m "Second commit"
