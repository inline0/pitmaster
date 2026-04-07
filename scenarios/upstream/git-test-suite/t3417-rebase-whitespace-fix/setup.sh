#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git commit --allow-empty -m "Initial empty commit" 2>/dev/null || true
git add file && git commit -m first 2>/dev/null || true
mv second file 2>/dev/null || true
git add file && git commit -m second 2>/dev/null || true
git rebase --whitespace=fix HEAD^^ 2>/dev/null || true
cp third file && git add file && git commit -m third 2>/dev/null || true
git rebase --whitespace=fix HEAD^^ 2>/dev/null || true
git config core.whitespace "-blank-at-eol" 2>/dev/null || true
git reset --hard HEAD^ 2>/dev/null || true
cp third file && git add file && git commit -m third 2>/dev/null || true
git rebase --whitespace=fix HEAD^^ 2>/dev/null || true

true
