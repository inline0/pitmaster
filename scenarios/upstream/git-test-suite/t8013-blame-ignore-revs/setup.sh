#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo line2 >>file
git add file 2>/dev/null || true
git commit -m B 2>/dev/null || true
git tag B 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m X 2>/dev/null || true
git tag X 2>/dev/null || true
git tag -a -m "X (annotated)" XT 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m Y 2>/dev/null || true
git tag Y 2>/dev/null || true
git config --add blame.ignoreRevsFile ignore_x 2>/dev/null || true
echo NOREV >ignore_norev
git config --add blame.markUnblamableLines true 2>/dev/null || true
echo "*" >expect
git config --add blame.markIgnoredLines true 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m Z 2>/dev/null || true
git tag Z 2>/dev/null || true
echo "?" >expect
