#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add a.txt b.txt delete.txt 2>/dev/null || true
git commit -m base 2>/dev/null || true
echo a >a.txt
echo b >b.txt
echo delete >delete.txt
git add a.txt b.txt delete.txt 2>/dev/null || true
git commit -m tip 2>/dev/null || true
git tag tip 2>/dev/null || true
echo change >a.txt
git commit -a -m side 2>/dev/null || true
git tag side 2>/dev/null || true
git merge --abort 2>/dev/null || true
git merge --abort 2>/dev/null || true
