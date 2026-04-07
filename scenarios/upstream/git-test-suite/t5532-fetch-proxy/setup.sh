#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo content >file
git add file 2>/dev/null || true
git commit -m one 2>/dev/null || true
git config core.gitproxy ./proxy 2>/dev/null || true
echo one >expect
