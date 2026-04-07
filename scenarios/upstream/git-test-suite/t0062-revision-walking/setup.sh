#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo a > a
git add a 2>/dev/null || true
git commit -m "add a" 2>/dev/null || true
echo b > b
git add b 2>/dev/null || true
git commit -m "add b" 2>/dev/null || true
