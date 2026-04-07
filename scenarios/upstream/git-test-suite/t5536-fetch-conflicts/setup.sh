#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m "Initial" 2>/dev/null || true
git branch branch1 2>/dev/null || true
git tag tag1 2>/dev/null || true
git commit --allow-empty -m "First" 2>/dev/null || true
git branch branch2 2>/dev/null || true
git tag tag2 2>/dev/null || true
