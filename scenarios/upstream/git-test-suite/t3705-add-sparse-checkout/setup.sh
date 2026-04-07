#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git add sparse_entry  2>/dev/null || true
git commit --allow-empty -m "ensure sparse_entry exists at HEAD"  2>/dev/null || true
echo "100644 $SPARSE_ENTRY_BLOB 0	sparse_entry" >expected
git add -A 2>stderr  2>/dev/null || true
echo . >>expect
echo modified >sparse_entry
