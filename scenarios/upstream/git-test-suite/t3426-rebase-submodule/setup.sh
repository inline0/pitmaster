#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git checkout -b ours HEAD  2>/dev/null || true
echo x >>file1
git add file1  2>/dev/null || true
git commit -m add_x  2>/dev/null || true
git checkout -b ours HEAD  2>/dev/null || true
echo x >>file1
