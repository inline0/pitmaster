#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

printf "no newline" > file.txt

git add file.txt
git commit -m "Initial commit without trailing newline"

printf "changed" > file.txt
