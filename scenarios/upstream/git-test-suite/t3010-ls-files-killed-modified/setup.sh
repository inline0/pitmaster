#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git commit --allow-empty -m "empty 1 (updated)" 2>/dev/null || true
