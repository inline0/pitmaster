#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

echo "File A content" > a.txt
echo "File B content" > b.txt
echo "File C content" > c.txt
git add a.txt b.txt c.txt
git commit -m "Add three files"
