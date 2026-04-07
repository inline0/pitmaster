#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo content >file
git add file 2>/dev/null || true
git commit -m base 2>/dev/null || true
git config core.protectHFS true 2>/dev/null || true
git config core.protectNTFS true 2>/dev/null || true
printf "100644 blob %s\t%s" "$blob" "$path" >tree
printf "040000 tree %s\t%s" "$tree" "$path" >tree
printf "100644 blob %s\t%s" "$blob" ".gi${u200c}t" >tree
