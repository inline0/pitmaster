#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
mkdir olddir/ other/
printf "%s\n" A B C D E F G >other/content
git add olddir other 2>/dev/null || true
git commit -m orig 2>/dev/null || true
git branch upstream 2>/dev/null || true
git branch topic 2>/dev/null || true
git add olddir 2>/dev/null || true
mkdir newdir
git mv olddir/valuesX newdir 2>/dev/null || true
git commit -m "Renamed (and modified) olddir/valuesX into newdir/" 2>/dev/null || true
git add olddir 2>/dev/null || true
git commit -m A 2>/dev/null || true
printf "%s\n" A B C D E F G H I >other/content
git add olddir/valuesY other 2>/dev/null || true
git commit -m B 2>/dev/null || true
git config merge.directoryRenames true 2>/dev/null || true
git update-ref --stdin <out 2>/dev/null || true
git checkout topic 2>/dev/null || true
