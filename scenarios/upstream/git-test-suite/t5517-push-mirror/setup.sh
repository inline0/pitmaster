#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo one >foo && git add foo && git commit -m one 2>/dev/null || true
echo one >foo && git add foo && git commit -m one 2>/dev/null || true
echo two >foo && git add foo && git commit -m two 2>/dev/null || true
echo one >foo && git add foo && git commit -m one 2>/dev/null || true
echo two >foo && git add foo && git commit -m two 2>/dev/null || true
git reset --hard HEAD^ 2>/dev/null || true

true
