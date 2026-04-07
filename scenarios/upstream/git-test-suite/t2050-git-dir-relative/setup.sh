#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo initial >top
git add top  2>/dev/null || true
git commit -m initial  2>/dev/null || true
echo changed >top
echo changed again >top
