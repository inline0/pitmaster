#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo A >fileA.t
echo B >fileB.t
echo C >fileC.t
echo D >fileD.t
git add fileA.t fileB.t fileC.t fileD.t 2>/dev/null || true
git commit -m "files" 2>/dev/null || true
git tag checkpoint 2>/dev/null || true
echo fileA.t >list
