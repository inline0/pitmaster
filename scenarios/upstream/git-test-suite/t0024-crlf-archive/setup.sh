#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config core.autocrlf true 2>/dev/null || true
printf "CRLF line ending\r\nAnd another\r\n" >sample
git add sample 2>/dev/null || true
git commit -m Initial 2>/dev/null || true
mkdir untarred
mkdir unzipped
