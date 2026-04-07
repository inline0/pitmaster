#!/bin/bash
set -e

# Create submodule source repo
mkdir sub-source
cd sub-source
git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "sub content" > sub.txt
git add sub.txt
git commit -m "sub initial"
cd ..

# Create main repo with submodule
git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "main" > main.txt
git add main.txt
git commit -m "main initial"
git -c protocol.file.allow=always submodule add "$(pwd)/sub-source" subdir
git commit -m "add submodule"
