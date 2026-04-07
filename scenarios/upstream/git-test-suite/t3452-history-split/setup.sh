#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add . 2>/dev/null || true
git commit -m split-me 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m split-me 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m split-me 2>/dev/null || true
echo a >a
echo b >b
git add . 2>/dev/null || true
git commit -m "initial commit" 2>/dev/null || true
echo a-modified >a
echo b-modified >b
git add b 2>/dev/null || true
