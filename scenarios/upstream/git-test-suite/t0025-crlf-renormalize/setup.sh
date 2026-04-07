#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config core.autocrlf false 2>/dev/null || true
printf "LINEONE\nLINETWO\nLINETHREE\n" >LF.txt
printf "LINEONE\r\nLINETWO\r\nLINETHREE\r\n" >CRLF.txt
printf "LINEONE\r\nLINETWO\nLINETHREE\n" >CRLF_mix_LF.txt
git add . 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo "*.txt text=auto" >.gitattributes
git add --renormalize "*.txt" 2>/dev/null || true
echo "*.txt text=auto" >.gitattributes
git add --ignore-errors "*.txt" 2>/dev/null || true
