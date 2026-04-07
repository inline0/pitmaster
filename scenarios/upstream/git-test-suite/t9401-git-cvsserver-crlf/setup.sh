#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config push.default matching 2>/dev/null || true
echo "Simple text file" >textfile.c 2>/dev/null || true
echo "File with embedded NUL: Q <- there" | q_to_nul > binfile.bin 2>/dev/null || true
mkdir subdir 2>/dev/null || true
echo "Another text file" > subdir/file.h 2>/dev/null || true
echo "Another binary: Q (this time CR)" | q_to_cr > subdir/withCr.bin 2>/dev/null || true
echo "Mixed up NUL, but marked text: Q <- there" | q_to_nul > mixedUp.c 2>/dev/null || true
echo "Unspecified" > subdir/unspecified.other 2>/dev/null || true
echo "/*.bin -crlf" > .gitattributes 2>/dev/null || true
echo "/*.c crlf" >> .gitattributes 2>/dev/null || true
echo "subdir/*.bin -crlf" >> .gitattributes 2>/dev/null || true
echo "subdir/*.c crlf" >> .gitattributes 2>/dev/null || true
echo "subdir/file.h crlf" >> .gitattributes 2>/dev/null || true
git add .gitattributes textfile.c binfile.bin mixedUp.c subdir/* 2>/dev/null || true
git commit -q -m "First Commit" 2>/dev/null || true

true
