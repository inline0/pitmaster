#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git commit --allow-empty -m foo 2>/dev/null || true
git commit --allow-empty -m foo  2>/dev/null || true
echo "$author_name" >expected
