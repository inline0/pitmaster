#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config core.quotepath off 2>/dev/null || true
git commit -m "Initial commit" --allow-empty 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "Added files" 2>/dev/null || true
