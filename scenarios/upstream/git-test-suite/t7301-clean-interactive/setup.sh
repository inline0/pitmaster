#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mkdir -p src 2>/dev/null || true
touch src/part1.c Makefile 2>/dev/null || true
echo build >.gitignore 2>/dev/null || true
echo \*.o >>.gitignore 2>/dev/null || true
git add . 2>/dev/null || true
git commit -m setup 2>/dev/null || true
touch src/part2.c README 2>/dev/null || true
git add . 2>/dev/null || true
mkdir -p build docs 2>/dev/null || true
touch a.out src/part3.c src/part3.h src/part4.c src/part4.h \ 2>/dev/null || true
mkdir -p build docs 2>/dev/null || true
touch a.out src/part3.c src/part3.h src/part4.c src/part4.h \ 2>/dev/null || true
mkdir -p build docs 2>/dev/null || true
touch a.out src/part3.c src/part3.h src/part4.c src/part4.h \ 2>/dev/null || true
mkdir -p build docs 2>/dev/null || true
touch a.out src/part3.c src/part3.h src/part4.c src/part4.h \ 2>/dev/null || true

true
