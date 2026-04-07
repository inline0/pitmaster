#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

printf "LINEONE\nLINETWO\nLINETHREE\n" >o1.txt 2>/dev/null || true
printf "LINEONE\r\nLINETWO\r\nLINETHREE\r\n" >o2.txt 2>/dev/null || true
printf "LINEONE\r\nLINETWO\nLINETHREE\n" >o3.txt 2>/dev/null || true
git add o?.txt 2>/dev/null || true
git update-index --add --cacheinfo 120000 $oid o4.txt 2>/dev/null || true
git update-index --add --cacheinfo 160000 $oid o5.txt 2>/dev/null || true
git update-index --add --cacheinfo 100755 $oid o6.txt 2>/dev/null || true
git commit -m base 2>/dev/null || true

true
