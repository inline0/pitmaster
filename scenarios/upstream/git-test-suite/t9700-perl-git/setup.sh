#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo "test file 1" >file1 2>/dev/null || true
echo "test file 2" >file2 2>/dev/null || true
mkdir directory1 2>/dev/null || true
echo "in directory1" >>directory1/file 2>/dev/null || true
mkdir directory2 2>/dev/null || true
echo "in directory2" >>directory2/file 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "first commit" 2>/dev/null || true
echo "new file in subdir 2" >directory2/file2 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m "commit in directory2" 2>/dev/null || true
echo "changed file 1" >file1 2>/dev/null || true
git commit -a -m "second commit" 2>/dev/null || true
git config --add color.test.slot1 green 2>/dev/null || true
git config --add test.string value 2>/dev/null || true
git config --add test.dupstring value1 2>/dev/null || true
git config --add test.dupstring value2 2>/dev/null || true
git config --add test.booltrue true 2>/dev/null || true
git config --add test.boolfalse no 2>/dev/null || true
git config --add test.boolother other 2>/dev/null || true
git config --add test.int 2k 2>/dev/null || true
git config --add test.path "~/foo" 2>/dev/null || true
git config --add test.pathexpanded "$HOME/foo" 2>/dev/null || true
git config --add test.pathmulti foo 2>/dev/null || true
git config --add test.pathmulti bar 2>/dev/null || true
git init --bare bare.git 2>/dev/null || true

true
