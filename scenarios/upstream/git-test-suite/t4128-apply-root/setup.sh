#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
mkdir -p some/sub/dir
echo Hello > some/sub/dir/file
git add some/sub/dir/file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git tag initial 2>/dev/null || true
echo Bello >expect
echo Bello >expect
echo Bello >expect
echo Bello >expect
echo Bello >expect
echo Bello >expect
echo content >expect
echo content >expect
echo content >some/sub/dir/delfile
git add some/sub/dir/delfile 2>/dev/null || true
echo content >expect
