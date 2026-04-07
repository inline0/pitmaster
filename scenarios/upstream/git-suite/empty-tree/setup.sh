#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
EMPTY_TREE=$(git hash-object -t tree /dev/null)
git commit --allow-empty -m "empty commit"
