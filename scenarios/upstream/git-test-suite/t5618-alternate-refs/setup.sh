#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git checkout -b one  2>/dev/null || true
git commit --allow-empty -m base  2>/dev/null || true
git commit --allow-empty -m one  2>/dev/null || true
git checkout -b two HEAD^  2>/dev/null || true
git commit --allow-empty -m two 2>/dev/null || true
git merge origin/one 2>/dev/null || true
