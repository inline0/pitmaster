#!/bin/bash
set -e

git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"

for i in 1 2 3 4 5; do
    echo "Commit $i" >> log.txt
    git add log.txt
    git commit -m "Commit number $i"
done
