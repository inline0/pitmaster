#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_write_lines 1 2 3 4 5 6 7 8 9 10 >numbers 2>/dev/null || true
test_write_lines A B C D E F G H I J >letters 2>/dev/null || true
git add numbers letters 2>/dev/null || true
git commit -m A 2>/dev/null || true
git branch upstream 2>/dev/null || true
git branch localmods 2>/dev/null || true
git checkout upstream 2>/dev/null || true
test_write_lines A B C D E >letters 2>/dev/null || true
git add letters 2>/dev/null || true
git commit -m B 2>/dev/null || true
test_write_lines 1 2 3 4 five 6 7 8 9 ten >numbers 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m C 2>/dev/null || true
git checkout localmods 2>/dev/null || true
test_write_lines 1 2 3 4 five 6 7 8 9 10 >numbers 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m C2 2>/dev/null || true
git commit --allow-empty -m D 2>/dev/null || true
test_write_lines A B C D E >letters 2>/dev/null || true
git add letters 2>/dev/null || true
git commit -m "Five letters ought to be enough for anybody" 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git rebase --apply upstream 2>/dev/null || true
test_write_lines D C B A >expect 2>/dev/null || true
git checkout -B testing localmods 2>/dev/null || true
git rebase --merge --empty=drop upstream 2>/dev/null || true
test_write_lines D C B A >expect 2>/dev/null || true

true
