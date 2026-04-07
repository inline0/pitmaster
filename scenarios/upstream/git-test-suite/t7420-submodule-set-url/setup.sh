#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config --global protocol.file.allow always 2>/dev/null || true
mkdir submodule
echo a >file
git add file 2>/dev/null || true
git commit -ma 2>/dev/null || true
mkdir namedsubmodule
echo 1 >file
git add file 2>/dev/null || true
git commit -m1 2>/dev/null || true
mkdir super
git commit -m "add submodules" 2>/dev/null || true
echo b >>file
git add file 2>/dev/null || true
git commit -mb 2>/dev/null || true
echo 2 >>file
git add file 2>/dev/null || true
git commit -m2 2>/dev/null || true
