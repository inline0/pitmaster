#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m empty 2>/dev/null || true
git tag empty 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m added 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m modified 2>/dev/null || true
git add . 2>/dev/null || true
