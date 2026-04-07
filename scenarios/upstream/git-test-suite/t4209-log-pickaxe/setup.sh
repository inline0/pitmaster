#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo Picked >file
git add file 2>/dev/null || true
git commit --author="Another Person <another@example.com>" -m second 2>/dev/null || true
echo "* diff=test" >.gitattributes
echo "* diff=test" >.gitattributes
echo "* diff=test" >.gitattributes
echo "* diff=test" >.gitattributes
echo "* diff=bin" >.gitattributes
