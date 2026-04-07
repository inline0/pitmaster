#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git update-ref $m $A 2>/dev/null || true
git symbolic-ref HEAD $m 2>/dev/null || true
git update-ref -m delete-$m -d $m 2>/dev/null || true
git update-ref $m $A 2>/dev/null || true
git symbolic-ref HEAD $m 2>/dev/null || true
git update-ref -m delete-by-head -d HEAD 2>/dev/null || true
git update-ref $outside $A 2>/dev/null || true
git update-ref --create-reflog $outside $A 2>/dev/null || true
git update-ref $outside $A 2>/dev/null || true
git update-ref $outside $A 2>/dev/null || true
git update-ref ORIG_HEAD $A 2>/dev/null || true
git update-ref --no-create-reflog $outside $A 2>/dev/null || true
git pack-refs --all 2>/dev/null || true
echo foo >foo.c 2>/dev/null || true
git add foo.c 2>/dev/null || true
git commit -m foo 2>/dev/null || true
git symbolic-ref SYMREF $m 2>/dev/null || true
git update-ref --no-deref -d SYMREF 2>/dev/null || true
echo foo >foo.c 2>/dev/null || true
git add foo.c 2>/dev/null || true
git commit -m foo 2>/dev/null || true
git symbolic-ref SYMREF $m 2>/dev/null || true
git pack-refs --all 2>/dev/null || true
git update-ref --no-deref -d SYMREF 2>/dev/null || true
git symbolic-ref refs/heads/self refs/heads/self 2>/dev/null || true
git symbolic-ref --no-recurse refs/heads/self 2>/dev/null || true
git symbolic-ref --no-recurse refs/heads/self 2>/dev/null || true
git symbolic-ref refs/heads/self refs/heads/self 2>/dev/null || true
git symbolic-ref --no-recurse refs/heads/self 2>/dev/null || true
git update-ref --no-deref -d refs/heads/self 2>/dev/null || true
git symbolic-ref refs/heads/ref-to-bad refs/heads/bad 2>/dev/null || true
git symbolic-ref --no-recurse refs/heads/ref-to-bad 2>/dev/null || true
git update-ref --no-deref -d refs/heads/ref-to-bad 2>/dev/null || true

true
