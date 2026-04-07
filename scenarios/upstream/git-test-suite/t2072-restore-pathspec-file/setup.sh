#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
mkdir dir1
echo 1 >dir1/file
echo 1 >fileA.t
echo 1 >fileB.t
echo 1 >fileC.t
echo 1 >fileD.t
git add dir1 fileA.t fileB.t fileC.t fileD.t 2>/dev/null || true
git commit -m "files 1" 2>/dev/null || true
echo 2 >dir1/file
echo 2 >fileA.t
echo 2 >fileB.t
echo 2 >fileC.t
echo 2 >fileD.t
git add dir1 fileA.t fileB.t fileC.t fileD.t 2>/dev/null || true
git commit -m "files 2" 2>/dev/null || true
git tag checkpoint 2>/dev/null || true
echo fileA.t >list
echo fileA.t >list
