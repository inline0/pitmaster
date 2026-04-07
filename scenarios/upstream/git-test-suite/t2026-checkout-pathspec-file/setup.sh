#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo 1 >fileA.t
echo 1 >fileB.t
echo 1 >fileC.t
echo 1 >fileD.t
git add fileA.t fileB.t fileC.t fileD.t 2>/dev/null || true
git commit -m "files 1" 2>/dev/null || true
echo 2 >fileA.t
echo 2 >fileB.t
echo 2 >fileC.t
echo 2 >fileD.t
git add fileA.t fileB.t fileC.t fileD.t 2>/dev/null || true
git commit -m "files 2" 2>/dev/null || true
git tag checkpoint 2>/dev/null || true
echo fileA.t >list
git checkout --pathspec-from-file=list HEAD^1 2>/dev/null || true
git checkout --pathspec-from-file=list HEAD^1 2>/dev/null || true
echo fileA.t >list
