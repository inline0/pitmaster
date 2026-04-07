#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config diff.color.old red 2>/dev/null || true
git config diff.color.new green 2>/dev/null || true
git config diff.color.func magenta 2>/dev/null || true
git config diff.testdriver.wordRegex "[^[:space:]]" 2>/dev/null || true
git config diff.wordRegex "[[:alnum:]]+" 2>/dev/null || true
echo "aaa (aaa)" >pre
echo "aaa (aaa) aaa" >post
echo "(:" >pre
echo "(" >post
echo "(:" >pre
echo "(" >post
printf "%s" "a a a a a" >pre
printf "%s" "a a ab a a" >post
echo "a b; c" >a.tex
echo "a b; c" >z.txt
git add a.tex z.txt 2>/dev/null || true
git commit -minitial 2>/dev/null || true
echo "a bx; c" >a.tex
echo "a bx; c" >z.txt
git commit -mmodified -a 2>/dev/null || true
echo "*.tex diff=tex" >.gitattributes
