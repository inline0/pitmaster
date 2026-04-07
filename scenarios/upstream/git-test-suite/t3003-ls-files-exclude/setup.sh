#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo content >file
git add file 2>/dev/null || true
git commit -m file 2>/dev/null || true
echo modification >file
echo '$2' >expect
echo file >.gitignore
echo content >other-file
git add other-file 2>/dev/null || true
echo file >expect
