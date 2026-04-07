#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

touch empty.txt

git add empty.txt
git commit -m "Commit an empty file"
