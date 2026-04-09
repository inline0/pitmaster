#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config core.eol lf 2>/dev/null || true
git read-tree --reset -u HEAD 2>/dev/null || true
git config core.eol crlf 2>/dev/null || true
git read-tree --reset -u HEAD 2>/dev/null || true
git config core.eol lf 2>/dev/null || true
git config core.autocrlf true 2>/dev/null || true
git read-tree --reset -u HEAD 2>/dev/null || true
git config --unset-all core.eol 2>/dev/null || true
git config core.autocrlf true 2>/dev/null || true
git read-tree --reset -u HEAD 2>/dev/null || true
printf "*.txt text\n" >.gitattributes 2>/dev/null || true
printf "one\r\ntwo\r\nthree\r\n" >filedos.txt 2>/dev/null || true
printf "one\ntwo\nthree\n" >fileunix.txt 2>/dev/null || true
git init 2>/dev/null || true
git config core.autocrlf false 2>/dev/null || true
git config core.eol native 2>/dev/null || true
git add filedos.txt fileunix.txt 2>/dev/null || true
git commit -m "first" 2>/dev/null || true
git reset --hard HEAD 2>/dev/null || true

true
