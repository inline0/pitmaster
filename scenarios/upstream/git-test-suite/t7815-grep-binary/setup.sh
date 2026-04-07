#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo Binary file a matches >expect 2>/dev/null || true
echo a:1 >expect 2>/dev/null || true
echo a >expect 2>/dev/null || true
echo a >expect 2>/dev/null || true
echo text >t 2>/dev/null || true
git add t 2>/dev/null || true
echo t:text >expect 2>/dev/null || true
echo "t -diff" >.gitattributes 2>/dev/null || true
echo "Binary file t matches" >expect 2>/dev/null || true
git add .gitattributes 2>/dev/null || true
rm .gitattributes 2>/dev/null || true
git commit -m new 2>/dev/null || true
echo "Binary file HEAD:t matches" >expect 2>/dev/null || true
echo binQary | q_to_nul >b 2>/dev/null || true
git add b 2>/dev/null || true
echo "Binary file b matches" >expect 2>/dev/null || true
echo "b diff" >.gitattributes 2>/dev/null || true
echo "b:binQary" >expect 2>/dev/null || true
echo a diff=foo >.gitattributes 2>/dev/null || true
git config diff.foo.textconv "\"$(pwd)\""/nul_to_q_textconv 2>/dev/null || true
echo "a:binaryQfileQm[*]cQ*æQð" >expect 2>/dev/null || true

true
