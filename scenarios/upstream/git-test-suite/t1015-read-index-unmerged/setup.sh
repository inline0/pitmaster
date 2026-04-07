#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add letters 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git checkout -b modify 2>/dev/null || true
echo i >>letters
echo "version 2" >letters.txt
git add letters letters.txt 2>/dev/null || true
git commit -m modified 2>/dev/null || true
git checkout -b delete HEAD^ 2>/dev/null || true
git rm letters 2>/dev/null || true
mkdir letters
echo "version 1" >letters.txt
git add letters letters.txt 2>/dev/null || true
git commit -m deleted 2>/dev/null || true
git checkout delete^0 2>/dev/null || true
git checkout delete^0 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git checkout -b d-edit 2>/dev/null || true
mkdir foo
echo content >foo/bar
git add foo 2>/dev/null || true
echo 11 >>numbers
git add numbers 2>/dev/null || true
git commit -m "directory and edit" 2>/dev/null || true
git checkout -b f-edit d-edit^1 2>/dev/null || true
echo content >foo
git add foo 2>/dev/null || true
echo eleven >>numbers
git add numbers 2>/dev/null || true
git commit -m "file and edit" 2>/dev/null || true
git checkout f-edit^0 2>/dev/null || true
git merge --abort 2>/dev/null || true
git checkout f-edit^0 2>/dev/null || true
