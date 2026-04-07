#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m "more" 2>message 2>/dev/null || true
git commit --allow-empty -m "even more" 2>message 2>/dev/null || true
git commit --allow-empty -m "even more" 2>message 2>/dev/null || true
git commit --allow-empty -m "even more" 2>message 2>/dev/null || true
