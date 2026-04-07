#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir -p some/sub/dir 2>/dev/null || true
echo Hello > some/sub/dir/file 2>/dev/null || true
git add some/sub/dir/file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git tag initial 2>/dev/null || true
echo Bello >expect 2>/dev/null || true
git reset --hard initial 2>/dev/null || true
echo Bello >expect 2>/dev/null || true

true
