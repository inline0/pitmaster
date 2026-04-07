#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git commit --allow-empty -m empty 2>/dev/null || true
git tag empty 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m added 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m modified 2>/dev/null || true
git add . 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true

true
