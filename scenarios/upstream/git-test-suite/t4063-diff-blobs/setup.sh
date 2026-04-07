#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo one >one
echo two >two
git add . 2>/dev/null || true
git commit -m base 2>/dev/null || true
