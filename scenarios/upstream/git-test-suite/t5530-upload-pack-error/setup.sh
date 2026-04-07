#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo file >file
git add file 2>/dev/null || true
git commit -a -m original 2>/dev/null || true
echo changed >file
git commit -a -m changed 2>/dev/null || true
printf "0000" >expect
echo "ACK $tree_id" >expect
