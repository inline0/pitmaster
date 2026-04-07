#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --global protocol.file.allow always 2>/dev/null || true
mkdir submodule 2>/dev/null || true
git init 2>/dev/null || true
echo a >a 2>/dev/null || true
git add . 2>/dev/null || true
git commit -ma 2>/dev/null || true
git checkout -b topic 2>/dev/null || true
echo b >a 2>/dev/null || true
git add . 2>/dev/null || true
git commit -mb 2>/dev/null || true
git checkout main 2>/dev/null || true
mkdir super 2>/dev/null || true
git init 2>/dev/null || true
git submodule add ../submodule 2>/dev/null || true
git submodule add --name thename ../submodule thepath 2>/dev/null || true
git commit -m "add submodules" 2>/dev/null || true

true
