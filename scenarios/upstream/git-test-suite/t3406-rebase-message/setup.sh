#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config set gc.reflogExpire never 2>/dev/null || true
git config set gc.reflogExpireUnreachable never 2>/dev/null || true
test_commit O fileO 2>/dev/null || true
test_commit X fileX 2>/dev/null || true
git branch fast-forward 2>/dev/null || true
test_commit A fileA 2>/dev/null || true
test_commit B fileB 2>/dev/null || true
test_commit Y fileY 2>/dev/null || true
git checkout -b conflicts O 2>/dev/null || true
test_commit P 2>/dev/null || true
test_commit conflict-X fileX 2>/dev/null || true
test_commit Q 2>/dev/null || true
git checkout -b topic O 2>/dev/null || true
git cherry-pick A B 2>/dev/null || true
test_commit Z fileZ 2>/dev/null || true
git tag start 2>/dev/null || true
git rebase -m main >actual 2>/dev/null || true
git rebase --apply main >out 2>/dev/null || true

true
