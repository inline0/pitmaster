#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
printf "%04096d" 0 >4096-zeroes.txt
git add 4096-zeroes.txt 2>/dev/null || true
git commit -m "A 4k file" 2>/dev/null || true
