#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit README 2>/dev/null || true
git init files 2>/dev/null || true
test_commit -C files topic_1 2>/dev/null || true
test_commit -C files topic_2 2>/dev/null || true
test_commit -C files topic_3 2>/dev/null || true
git merge -s ours --no-commit --allow-unrelated-histories \ 2>/dev/null || true
git read-tree --prefix=files_subtree -u files-main 2>/dev/null || true
git commit -m "Add subproject main" 2>/dev/null || true
test_commit -C files_subtree topic_4 2>/dev/null || true
test_commit files_subtree/topic_5 2>/dev/null || true
git checkout -b to-rebase 2>/dev/null || true
git reset --hard 2>/dev/null || true
git commit -m "Empty commit" --allow-empty 2>/dev/null || true
git checkout -b rebase-onto to-rebase 2>/dev/null || true
git rebase --skip 2>/dev/null || true
git checkout -b rebase-merges-onto to-rebase 2>/dev/null || true
git rebase --skip 2>/dev/null || true

true
