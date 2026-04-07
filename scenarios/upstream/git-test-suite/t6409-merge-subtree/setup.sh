#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git merge -s subtree side 2>/dev/null || true
test_write_lines mundo $s world >expect 2>/dev/null || true
git checkout --orphan sub 2>/dev/null || true
git rm -rf . 2>/dev/null || true
test_commit foo 2>/dev/null || true
git checkout -b topic main 2>/dev/null || true
git merge -s ours --no-commit --allow-unrelated-histories sub 2>/dev/null || true
git read-tree --prefix=dir/ -u sub 2>/dev/null || true
git commit -m "initial merge of sub into topic" 2>/dev/null || true

true
