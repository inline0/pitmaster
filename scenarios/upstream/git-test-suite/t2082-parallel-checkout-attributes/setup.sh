#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init ident 2>/dev/null || true
echo "A ident" >.gitattributes 2>/dev/null || true
echo "\$Id\$" >A 2>/dev/null || true
echo "\$Id\$" >B 2>/dev/null || true
git add -A 2>/dev/null || true
git commit -m id 2>/dev/null || true
git init encoding 2>/dev/null || true
echo text >utf8-text 2>/dev/null || true
echo "A working-tree-encoding=UTF-16" >.gitattributes 2>/dev/null || true
cp utf16-text A 2>/dev/null || true
cp utf8-text B 2>/dev/null || true
git add A B .gitattributes 2>/dev/null || true
git commit -m encoding 2>/dev/null || true
git init eol 2>/dev/null || true
printf "multi\r\nline\r\ntext" >crlf-text 2>/dev/null || true
printf "multi\nline\ntext" >lf-text 2>/dev/null || true
git config core.autocrlf false 2>/dev/null || true
echo "A eol=crlf" >.gitattributes 2>/dev/null || true
cp crlf-text A 2>/dev/null || true
cp lf-text B 2>/dev/null || true
git add A B .gitattributes 2>/dev/null || true
git commit -m eol 2>/dev/null || true

true
