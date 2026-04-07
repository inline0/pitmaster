#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git commit --allow-empty -m "Initial empty commit"  2>/dev/null || true
git add file  git commit -m first  2>/dev/null || true
git add file  git commit -m second  2>/dev/null || true
git commit -m beginning file  2>/dev/null || true
git commit -m more file  2>/dev/null || true
