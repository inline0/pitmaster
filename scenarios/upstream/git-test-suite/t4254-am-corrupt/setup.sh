#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo a >f
git add f 2>/dev/null || true
git commit -m initial 2>/dev/null || true
