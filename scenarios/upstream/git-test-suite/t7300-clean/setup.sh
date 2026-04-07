#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir -p src 2>/dev/null || true
touch src/part1.c Makefile 2>/dev/null || true
echo build >.gitignore 2>/dev/null || true
echo \*.o >>.gitignore 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m setup 2>/dev/null || true
touch src/part2.c README 2>/dev/null || true
git add . 2>/dev/null || true
git update-index --skip-worktree .gitignore 2>/dev/null || true
mkdir -p build docs 2>/dev/null || true
touch a.out src/part3.c docs/manual.txt obj.o build/lib.so 2>/dev/null || true
git update-index --no-skip-worktree .gitignore 2>/dev/null || true
git checkout .gitignore 2>/dev/null || true
mkdir -p build docs 2>/dev/null || true
touch a.out src/part3.c docs/manual.txt obj.o build/lib.so 2>/dev/null || true

true
