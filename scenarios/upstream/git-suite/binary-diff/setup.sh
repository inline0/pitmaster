#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
printf '\x00\x01\x02\x03binary content\x04\x05' > binary.dat
echo "text" > text.txt
git add binary.dat text.txt
git commit -m "initial with binary"
printf '\x00\x01\x02\x03modified binary\x04\x05\x06' > binary.dat
echo "modified text" > text.txt
git add binary.dat text.txt
git commit -m "modify both"
