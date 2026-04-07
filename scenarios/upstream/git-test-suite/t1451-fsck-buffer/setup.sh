#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m foo 2>/dev/null || true
printf "100644 foo\0\1\1\1\1" >input
