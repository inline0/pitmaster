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
echo c$i > c$i.c
git add c$i.c 2>/dev/null || true
git commit -m c$i 2>/dev/null || true
git tag c$i 2>/dev/null || true
git merge $refs 2>/dev/null || true
git merge c2 c3 c4 >actual 2>/dev/null || true
git merge c1 c2 >actual 2>/dev/null || true
