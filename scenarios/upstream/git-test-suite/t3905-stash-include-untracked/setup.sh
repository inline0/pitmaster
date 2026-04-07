#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo 1 >file
git add file  2>/dev/null || true
git commit -m initial  2>/dev/null || true
echo 2 >file
git add file  2>/dev/null || true
echo 3 >file
