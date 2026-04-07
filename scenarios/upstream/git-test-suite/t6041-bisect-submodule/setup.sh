#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "foo" >bar
git add bar  2>/dev/null || true
git commit -m "bisect bad"  2>/dev/null || true
