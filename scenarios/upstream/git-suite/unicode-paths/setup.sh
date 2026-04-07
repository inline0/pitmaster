#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config core.quotePath false
echo "ascii" > ascii.txt
echo "umlaut" > "über.txt"
echo "emoji" > "readme-🎉.txt"
echo "chinese" > "文件.txt"
git add .
git commit -m "unicode paths"
