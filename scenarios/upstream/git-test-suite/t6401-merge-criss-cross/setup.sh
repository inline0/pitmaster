#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_write_lines 1 2 3 4 5 6 7 8 9 >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m "Initial commit" file 2>/dev/null || true
git branch A 2>/dev/null || true
git branch B 2>/dev/null || true
git checkout A 2>/dev/null || true
test_write_lines 1 2 3 4 5 6 7 "8 changed in B8, branch A" 9 >file 2>/dev/null || true
git commit -m "B8" file 2>/dev/null || true
git checkout B 2>/dev/null || true
test_write_lines 1 2 "3 changed in C3, branch B" 4 5 6 7 8 9 >file 2>/dev/null || true
git commit -m "C3" file 2>/dev/null || true
git branch C3 2>/dev/null || true
git merge -m "pre E3 merge" A 2>/dev/null || true
test_write_lines 1 2 "3 changed in E3, branch B. New file size" 4 5 6 7 "8 changed in B8, branch A" 9 >file 2>/dev/null || true
git commit -m "E3" file 2>/dev/null || true
git checkout A 2>/dev/null || true
git merge -m "pre D8 merge" C3 2>/dev/null || true
test_write_lines 1 2 "3 changed in C3, branch B" 4 5 6 7 "8 changed in D8, branch A. New file size 2" 9 >file 2>/dev/null || true
git commit -m D8 file 2>/dev/null || true
git merge -m "final merge" B 2>/dev/null || true
cat <<-\EOF >file-expect 2>/dev/null || true

true
