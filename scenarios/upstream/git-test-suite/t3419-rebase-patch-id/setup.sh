#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git commit --allow-empty -m initial 2>/dev/null || true
git tag root 2>/dev/null || true
git checkout -q -f main 2>/dev/null || true
git reset --hard root 2>/dev/null || true
git add file 2>/dev/null || true
git commit -q -m initial 2>/dev/null || true
git branch -f other 2>/dev/null || true
git add file 2>/dev/null || true
git commit -q -m "change big file" 2>/dev/null || true
git checkout -q other 2>/dev/null || true
git add newfile 2>/dev/null || true
git commit -q -m "add small file" 2>/dev/null || true
git cherry-pick main >/dev/null 2>&1 2>/dev/null || true
git branch -f squashed main 2>/dev/null || true
git checkout -q -f squashed 2>/dev/null || true
git reset -q --soft HEAD~2 2>/dev/null || true
git commit -q -m squashed 2>/dev/null || true
git branch -f mode main 2>/dev/null || true
git checkout -q -f mode 2>/dev/null || true
git commit -q -a --amend 2>/dev/null || true
git branch -f modeother other 2>/dev/null || true
git checkout -q -f modeother 2>/dev/null || true
git commit -q -a --amend 2>/dev/null || true
git checkout -q main^{} 2>/dev/null || true
git add file 2>/dev/null || true
git commit -q -m "change big file again" 2>/dev/null || true
git checkout -q other^{} 2>/dev/null || true
git rebase main 2>/dev/null || true

true
