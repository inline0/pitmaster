#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo foo > foo
git add .  2>/dev/null || true
git commit -m "Initial commit" 2>/dev/null || true
git commit -m "Moved to sub/" 2>/dev/null || true
