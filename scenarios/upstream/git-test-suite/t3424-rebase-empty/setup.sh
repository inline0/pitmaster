#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add numbers letters 2>/dev/null || true
git commit -m A 2>/dev/null || true
git branch upstream 2>/dev/null || true
git branch localmods 2>/dev/null || true
git checkout upstream 2>/dev/null || true
git add letters 2>/dev/null || true
git commit -m B 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m C 2>/dev/null || true
git checkout localmods 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m C2 2>/dev/null || true
git commit --allow-empty -m D 2>/dev/null || true
git add letters 2>/dev/null || true
git commit -m "Five letters ought to be enough for anybody" 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods~2 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
