#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo $HOBBES >&2
git commit --allow-empty -m with 2>err 2>/dev/null || true
