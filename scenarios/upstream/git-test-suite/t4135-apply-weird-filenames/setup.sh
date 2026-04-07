#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m preimage 2>/dev/null || true
git tag preimage 2>/dev/null || true
git checkout -f preimage^0 2>/dev/null || true
echo postimage >expected
echo postimage >expected
echo preimage >'$postimage'
echo postimage >expected
echo postimage >expected
echo postimage >expected
echo postimage >expected
