#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
printf "line1\r\nline2\r\nline3\r\n" > crlf.txt
printf "line1\nline2\nline3\n" > lf.txt
printf "line1\r\nline2\nline3\r\n" > mixed.txt
git add crlf.txt lf.txt mixed.txt
git commit -m "files with different line endings"
