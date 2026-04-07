#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo c0 > c0.c
git add c0.c 2>/dev/null || true
git commit -m c0 2>/dev/null || true
git tag c0 2>/dev/null || true
echo c1 > c1.c
git add c1.c 2>/dev/null || true
git commit -m c1 2>/dev/null || true
git tag c1 2>/dev/null || true
echo c2 > c2.c
git add c2.c 2>/dev/null || true
git commit -m c2 2>/dev/null || true
git tag c2 2>/dev/null || true
echo c3 > c3.c
git add c3.c 2>/dev/null || true
git commit -m c3 2>/dev/null || true
git tag c3 2>/dev/null || true
echo c4 > c4.c
git add c4.c 2>/dev/null || true
git commit -m c4 2>/dev/null || true
git tag c4 2>/dev/null || true
echo c5 > c5.c
git add c5.c 2>/dev/null || true
git commit -m c5 2>/dev/null || true
git tag c5 2>/dev/null || true
git merge c2 c3 c4 c5 2>/dev/null || true
echo $i > $i.c
git add $i.c 2>/dev/null || true
git commit -m $i 2>/dev/null || true
git tag $i || return 1 2>/dev/null || true
echo $i > $i.c
git add $i.c 2>/dev/null || true
git commit -m $i 2>/dev/null || true
git tag $i || return 1 2>/dev/null || true
git merge E I 2>/dev/null || true
echo foo > file.c
git add file.c 2>/dev/null || true
git commit -m E2 2>/dev/null || true
git tag E2 2>/dev/null || true
echo bar >file.c
git add file.c 2>/dev/null || true
git commit -m I2 2>/dev/null || true
git tag I2 2>/dev/null || true
echo baz > file.c
git add file.c 2>/dev/null || true
git commit -m "resolve conflict" 2>/dev/null || true
git merge c4 c5 2>/dev/null || true
git merge c0 c4 2>/dev/null || true
