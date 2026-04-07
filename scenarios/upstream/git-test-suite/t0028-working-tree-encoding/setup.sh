#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config core.eol lf 2>/dev/null || true
echo "*.utf16 text working-tree-encoding=utf-16" >.gitattributes 2>/dev/null || true
echo "*.utf16lebom text working-tree-encoding=UTF-16LE-BOM" >>.gitattributes 2>/dev/null || true
printf "$text" >test.utf8.raw 2>/dev/null || true
printf "$text" | write_utf16 >test.utf16.raw 2>/dev/null || true
printf "$text" | write_utf32 >test.utf32.raw 2>/dev/null || true
printf "\377\376"                         >test.utf16lebom.raw 2>/dev/null || true
printf "$text" | iconv -f UTF-8 -t UTF-16LE >>test.utf16lebom.raw 2>/dev/null || true
printf "one\ntwo\nthree\n" >lf.utf8.raw 2>/dev/null || true
printf "one\r\ntwo\r\nthree\r\n" >crlf.utf8.raw 2>/dev/null || true
printf "\0a\0b\0c"                         >nobom.utf16be.raw 2>/dev/null || true
printf "a\0b\0c\0"                         >nobom.utf16le.raw 2>/dev/null || true
printf "\376\377\0a\0b\0c"                 >bebom.utf16be.raw 2>/dev/null || true
printf "\377\376a\0b\0c\0"                 >lebom.utf16le.raw 2>/dev/null || true
printf "\0\0\0a\0\0\0b\0\0\0c"             >nobom.utf32be.raw 2>/dev/null || true
printf "a\0\0\0b\0\0\0c\0\0\0"             >nobom.utf32le.raw 2>/dev/null || true
printf "\0\0\376\377\0\0\0a\0\0\0b\0\0\0c" >bebom.utf32be.raw 2>/dev/null || true
printf "\377\376\0\0a\0\0\0b\0\0\0c\0\0\0" >lebom.utf32le.raw 2>/dev/null || true
cp test.utf16.raw test.utf16 2>/dev/null || true
cp test.utf32.raw test.utf32 2>/dev/null || true
cp test.utf16lebom.raw test.utf16lebom 2>/dev/null || true
git add .gitattributes test.utf16 test.utf16lebom 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git checkout test.utf16 2>/dev/null || true

true
