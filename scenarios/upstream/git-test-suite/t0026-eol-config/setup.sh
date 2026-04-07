#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config core.autocrlf false 2>/dev/null || true
echo "one text" > .gitattributes
git add . 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git config core.eol lf 2>/dev/null || true
git config core.eol crlf 2>/dev/null || true
git config core.eol lf 2>/dev/null || true
git config core.autocrlf true 2>/dev/null || true
git config --unset-all core.eol 2>/dev/null || true
git config core.autocrlf true 2>/dev/null || true
printf "*.txt text\n" >.gitattributes
printf "one\r\ntwo\r\nthree\r\n" >filedos.txt
printf "one\ntwo\nthree\n" >fileunix.txt
git config core.autocrlf false 2>/dev/null || true
git config core.eol native 2>/dev/null || true
git add filedos.txt fileunix.txt 2>/dev/null || true
git commit -m "first" 2>/dev/null || true
