#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo A > file
git add file 2>/dev/null || true
git commit -m First 2>/dev/null || true
echo B >> file
git commit -a -m Second 2>/dev/null || true
echo C >> file
git commit -a -m Third 2>/dev/null || true
git config format.numbered true 2>/dev/null || true
git config format.numbered auto 2>/dev/null || true
