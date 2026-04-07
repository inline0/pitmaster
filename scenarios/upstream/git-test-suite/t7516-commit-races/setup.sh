#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m hare 2>/dev/null || true
git commit --allow-empty -m airplane 2>/dev/null || true
git checkout --orphan branch 2>/dev/null || true
git commit --allow-empty -m base 2>/dev/null || true
