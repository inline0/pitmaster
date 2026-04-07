#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add numbers 2>/dev/null || true
git commit -m base 2>/dev/null || true
git branch feature_a 2>/dev/null || true
git branch feature_b 2>/dev/null || true
git branch feature_c 2>/dev/null || true
git branch ab_resolution 2>/dev/null || true
git branch bc_resolution 2>/dev/null || true
git checkout feature_a 2>/dev/null || true
git commit -a -m change_a 2>/dev/null || true
git checkout feature_b 2>/dev/null || true
git commit -a -m change_b 2>/dev/null || true
git checkout feature_c 2>/dev/null || true
git commit -a -m change_c 2>/dev/null || true
git checkout bc_resolution 2>/dev/null || true
git merge --ff-only feature_b 2>/dev/null || true
git merge feature_c 2>/dev/null || true
git checkout ab_resolution 2>/dev/null || true
git merge --ff-only feature_a 2>/dev/null || true
git add numbers 2>/dev/null || true
git merge --continue 2>/dev/null || true
git add numbers letters content 2>/dev/null || true
git commit -m base 2>/dev/null || true
git branch side1 2>/dev/null || true
git branch side2 2>/dev/null || true
git checkout side1 2>/dev/null || true
git mv letters letters_side1 2>/dev/null || true
git mv content file_or_directory 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m side1 2>/dev/null || true
git checkout side2 2>/dev/null || true
git rm numbers 2>/dev/null || true
git mv letters letters_side2 2>/dev/null || true
mkdir file_or_directory
echo hello >file_or_directory/world
git add file_or_directory/world 2>/dev/null || true
git commit -m side2 2>/dev/null || true
git checkout -b resolution side1 2>/dev/null || true
git add numbers 2>/dev/null || true
git add letters_side1 2>/dev/null || true
git rm letters 2>/dev/null || true
git rm letters_side2 2>/dev/null || true
git add file_or_directory~HEAD 2>/dev/null || true
git mv file_or_directory~HEAD wanted_content 2>/dev/null || true
git commit -m resolved 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m base 2>/dev/null || true
git branch newside1 2>/dev/null || true
git branch newside2 2>/dev/null || true
git checkout newside1 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m side1 2>/dev/null || true
git checkout newside2 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m side2 2>/dev/null || true
git checkout -b newresolution newside1 2>/dev/null || true
git checkout --theirs numbers 2>/dev/null || true
git add -u numbers 2>/dev/null || true
git commit -m resolved 2>/dev/null || true
