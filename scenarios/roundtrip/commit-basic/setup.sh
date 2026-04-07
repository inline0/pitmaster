#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

echo "Hello from Pitmaster" > hello.txt
echo "Another file" > readme.md

git add hello.txt readme.md
git commit -m "Initial commit via git"
