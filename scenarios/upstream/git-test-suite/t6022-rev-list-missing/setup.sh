#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit 1 2>/dev/null || true
test_commit 2 2>/dev/null || true
test_commit 3 2>/dev/null || true
git tag -m "tag message" annot_tag HEAD~1 2>/dev/null || true
git tag regul_tag HEAD~1 2>/dev/null || true
git branch a_branch HEAD~1 2>/dev/null || true
echo $(git rev-parse HEAD:1.t) >>expect.raw 2>/dev/null || true
echo $(git rev-parse HEAD:2.t) >>expect.raw 2>/dev/null || true
echo "$missing_oid" >>expect.raw 2>/dev/null || true
mv "$path" "$path.hidden" 2>/dev/null || true
echo ?$oid >>expect.raw 2>/dev/null || true
git init missing-info 2>/dev/null || true
git commit --allow-empty -m first 2>/dev/null || true
mkdir foo 2>/dev/null || true
echo bar >foo/bar 2>/dev/null || true
echo baz >"baz baz" 2>/dev/null || true
echo bat >bat\" 2>/dev/null || true
git add -A 2>/dev/null || true
git commit -m second 2>/dev/null || true
echo "?$oid$path_info$type_info" >>expect.raw 2>/dev/null || true
mv "$path" "$path.hidden" 2>/dev/null || true

true
