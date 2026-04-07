#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "content" > file.txt
git add file.txt
git commit -m "initial"
git branch branch-one
git branch branch-two
git branch branch-three
git tag -a v1.0 -m "annotated tag"
git tag lightweight-tag
git pack-refs --all --prune
echo "more" >> file.txt
git add file.txt
git commit -m "after pack"
git branch branch-after-pack
