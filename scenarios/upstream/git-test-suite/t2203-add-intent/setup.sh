#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add -N one 2>/dev/null || true
git add -N two 2>/dev/null || true
echo contents >first
git add first 2>/dev/null || true
git commit -m first 2>/dev/null || true
git add -N third 2>/dev/null || true
echo contents >first
git add first 2>/dev/null || true
git commit -m first 2>/dev/null || true
git mv first second 2>/dev/null || true
git add -N third 2>/dev/null || true
echo "$content" >not-empty
git add -N empty not-empty 2>/dev/null || true
echo new >new-ita
git add -N new-ita 2>/dev/null || true
echo new >new-ita
git add -N new-ita 2>/dev/null || true
