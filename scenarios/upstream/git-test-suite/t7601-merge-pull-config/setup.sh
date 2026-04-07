#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo c0 >c0.c
git add c0.c 2>/dev/null || true
git commit -m c0 2>/dev/null || true
git tag c0 2>/dev/null || true
echo c1 >c1.c
git add c1.c 2>/dev/null || true
git commit -m c1 2>/dev/null || true
git tag c1 2>/dev/null || true
echo c2 >c2.c
git add c2.c 2>/dev/null || true
git commit -m c2 2>/dev/null || true
git tag c2 2>/dev/null || true
echo c3 >c3.c
git add c3.c 2>/dev/null || true
git commit -m c3 2>/dev/null || true
git tag c3 2>/dev/null || true
