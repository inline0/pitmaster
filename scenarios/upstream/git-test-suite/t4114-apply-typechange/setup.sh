#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo "hello world" > foo 2>/dev/null || true
echo "hi planet" > bar 2>/dev/null || true
git update-index --add foo bar 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git branch initial 2>/dev/null || true
git commit -m "foo symlinked to bar" 2>/dev/null || true
git branch foo-symlinked-to-bar 2>/dev/null || true
git rm -f foo 2>/dev/null || true
echo "how far is the sun?" > foo 2>/dev/null || true
git update-index --add foo 2>/dev/null || true
git commit -m "foo back to file" 2>/dev/null || true
git branch foo-back-to-file 2>/dev/null || true
printf "\0" > foo 2>/dev/null || true
git update-index foo 2>/dev/null || true
git commit -m "foo becomes binary" 2>/dev/null || true
git branch foo-becomes-binary 2>/dev/null || true
git update-index --remove foo 2>/dev/null || true
mkdir foo 2>/dev/null || true
echo "if only I knew" > foo/baz 2>/dev/null || true
git update-index --add foo/baz 2>/dev/null || true
git commit -m "foo becomes a directory" 2>/dev/null || true
git branch "foo-becomes-a-directory" 2>/dev/null || true
echo "hello world" > foo/baz 2>/dev/null || true
git update-index foo/baz 2>/dev/null || true
git commit -m "foo/baz is the original foo" 2>/dev/null || true
git branch foo-baz-renamed-from-foo 2>/dev/null || true
git checkout -f initial 2>/dev/null || true
git checkout -f foo-baz-renamed-from-foo 2>/dev/null || true

true
