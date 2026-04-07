#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add empty 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git commit --allow-empty -m "empty commit" 2>/dev/null || true
mkdir "funny "
git add "funny /empty" 2>/dev/null || true
