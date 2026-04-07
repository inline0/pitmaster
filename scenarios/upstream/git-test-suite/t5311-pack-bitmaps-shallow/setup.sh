#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init 2>/dev/null || true
git config pack.writeBitmapLookupTable '"$writeLookupTable"' 2>/dev/null || true
echo 1 >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m orig 2>/dev/null || true
echo 2 >file 2>/dev/null || true
git commit -a -m update 2>/dev/null || true
echo 1 >file 2>/dev/null || true
git commit -a -m repeat 2>/dev/null || true

true
