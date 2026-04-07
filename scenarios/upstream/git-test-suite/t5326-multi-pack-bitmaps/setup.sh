#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init 2>/dev/null || true
git config pack.writeBitmapLookupTable '"$writeLookupTable"' 2>/dev/null || true
git config maintenance.auto false 2>/dev/null || true
git init repo 2>/dev/null || true
git config pack.writeBitmapLookupTable '"$writeLookupTable"' 2>/dev/null || true
test_commit loose 2>/dev/null || true
test_commit packed 2>/dev/null || true
git init repo 2>/dev/null || true
git config pack.writeBitmapLookupTable '"$writeLookupTable"' 2>/dev/null || true
test_commit base 2>/dev/null || true
test_commit new 2>/dev/null || true

true
