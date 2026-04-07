#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config core.autocrlf true 2>/dev/null || true
echo foo >bar
git add bar 2>/dev/null || true
git commit -m initial 2>/dev/null || true
