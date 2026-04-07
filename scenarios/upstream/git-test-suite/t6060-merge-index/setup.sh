#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_write_lines 1 2 3 4 5 6 7 8 9 10 >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m base 2>/dev/null || true
git tag base 2>/dev/null || true
mv tmp file 2>/dev/null || true
git commit -a -m two 2>/dev/null || true
git tag two 2>/dev/null || true
git checkout -b other HEAD^ 2>/dev/null || true
mv tmp file 2>/dev/null || true
git commit -a -m ten 2>/dev/null || true
git tag ten 2>/dev/null || true
git read-tree -i -m base ten two 2>/dev/null || true
echo file >expect 2>/dev/null || true
git merge-index git-merge-one-file -a 2>/dev/null || true

true
