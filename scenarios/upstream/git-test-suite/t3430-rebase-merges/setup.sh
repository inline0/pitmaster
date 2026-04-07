#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mv "$1" "$(git rev-parse --git-path ORIGINAL-TODO)" 2>/dev/null || true
cp script-from-scratch "$1" 2>/dev/null || true
test_commit A 2>/dev/null || true
git checkout -b first 2>/dev/null || true
test_commit B 2>/dev/null || true
git checkout main 2>/dev/null || true
test_commit C 2>/dev/null || true
test_commit D 2>/dev/null || true
git merge --no-commit B 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m E 2>/dev/null || true
git tag -m E E 2>/dev/null || true
git checkout -b second C 2>/dev/null || true
test_commit F 2>/dev/null || true
test_commit G 2>/dev/null || true
git checkout main 2>/dev/null || true
git merge --no-commit G 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m H 2>/dev/null || true
git tag -m H H 2>/dev/null || true
git checkout A 2>/dev/null || true
test_commit conflicting-G G.t 2>/dev/null || true
cat >script-from-scratch <<-\EOF 2>/dev/null || true
test_tick 2>/dev/null || true
git rebase -i -r A main 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
