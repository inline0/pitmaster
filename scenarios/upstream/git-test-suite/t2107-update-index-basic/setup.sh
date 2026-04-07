#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo content >file
git add file  2>/dev/null || true
git add submodule  2>/dev/null || true
echo content >file
git add A B  2>/dev/null || true
git commit --allow-empty -m snap  2>/dev/null || true
