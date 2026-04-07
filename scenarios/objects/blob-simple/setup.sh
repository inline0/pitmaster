#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

echo "Hello, World!" > hello.txt
git add hello.txt
git commit -m "Add hello.txt"
