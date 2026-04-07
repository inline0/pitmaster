#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit c0 c0.c 2>/dev/null || true
test_commit c1 c1.c 2>/dev/null || true
test_commit c1a c1.c "c1 a" 2>/dev/null || true
git reset --hard c0 2>/dev/null || true
test_commit c2 c2.c 2>/dev/null || true
git reset --hard c0 2>/dev/null || true
mkdir sub 2>/dev/null || true
echo "sub/f" > sub/f 2>/dev/null || true
mkdir sub2 2>/dev/null || true
echo "sub2/f" > sub2/f 2>/dev/null || true
git add sub/f sub2/f 2>/dev/null || true
git commit -m sub 2>/dev/null || true
git tag sub 2>/dev/null || true
echo "VERY IMPORTANT CHANGES" > important 2>/dev/null || true
git reset --hard c1 2>/dev/null || true
cp important c2.c 2>/dev/null || true
git reset --hard c1 2>/dev/null || true
cp important c2.c 2>/dev/null || true
git add c2.c 2>/dev/null || true
git commit -m important 2>/dev/null || true
git checkout c2 2>/dev/null || true

true
