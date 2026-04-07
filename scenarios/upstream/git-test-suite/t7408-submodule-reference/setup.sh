#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config --global protocol.file.allow always 2>/dev/null || true
echo first >file1
git add file1 2>/dev/null || true
git commit -m A-initial 2>/dev/null || true
echo second >file2
git add file2 2>/dev/null || true
git commit -m B-addition 2>/dev/null || true
echo file >file
git add file 2>/dev/null || true
git commit -m B-super-initial 2>/dev/null || true
git commit -m B-super-added 2>/dev/null || true
git commit -m B-super-added 2>/dev/null || true
echo "0 objects, 0 kilobytes" >expected
echo "I am super super." >file
git add file 2>/dev/null || true
git commit -m B-super-super-initial 2>/dev/null || true
git commit -m B-super-super-added 2>/dev/null || true
