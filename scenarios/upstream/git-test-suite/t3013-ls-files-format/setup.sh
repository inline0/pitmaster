#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
printf "LINEONE\nLINETWO\nLINETHREE\n" >o1.txt
printf "LINEONE\r\nLINETWO\r\nLINETHREE\r\n" >o2.txt
printf "LINEONE\r\nLINETWO\nLINETHREE\n" >o3.txt
git add o?.txt 2>/dev/null || true
git commit -m base 2>/dev/null || true
mkdir sub
echo change >o1.txt
echo o7 >o7.txt
git add o7.txt 2>/dev/null || true
