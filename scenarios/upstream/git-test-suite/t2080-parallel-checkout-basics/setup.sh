#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git checkout -b B2 2>/dev/null || true
echo B2 >file
git add file 2>/dev/null || true
git commit -m file 2>/dev/null || true
git checkout -b B1 2>/dev/null || true
echo B1 >file
git add file 2>/dev/null || true
git commit -m file 2>/dev/null || true
git checkout -b B1 2>/dev/null || true
mkdir a c e
echo a/a >a/a
echo b >b
echo c/c >c/c
echo e/e >e/e
echo "B1 i" >i
mkdir l
echo l/l >l/l
git add . 2>/dev/null || true
git commit -m B1 2>/dev/null || true
git checkout -b B2 2>/dev/null || true
git rm -rf :^.gitmodules :^k 2>/dev/null || true
mkdir b d f
echo a >a
echo b/b >b/b
echo d/d >d/d
echo f/f >f/f
echo "B2 i" >i
mkdir m
echo m/m >m/m
git add . 2>/dev/null || true
git commit -m B2 2>/dev/null || true
git checkout --recurse-submodules B1 2>/dev/null || true
