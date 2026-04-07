#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --global protocol.file.allow always 2>/dev/null || true
mkdir submodule 2>/dev/null || true
git init 2>/dev/null || true
echo a >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -ma 2>/dev/null || true
mkdir namedsubmodule 2>/dev/null || true
git init 2>/dev/null || true
echo 1 >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m1 2>/dev/null || true
mkdir super 2>/dev/null || true
git init 2>/dev/null || true
git submodule add ../submodule 2>/dev/null || true
git submodule add --name thename ../namedsubmodule thepath 2>/dev/null || true
git commit -m "add submodules" 2>/dev/null || true
echo b >>file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -mb 2>/dev/null || true
mv submodule newsubmodule 2>/dev/null || true
echo 2 >>file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m2 2>/dev/null || true
mv namedsubmodule newnamedsubmodule 2>/dev/null || true
git submodule set-url submodule ../newsubmodule 2>/dev/null || true
git submodule set-url thepath ../newnamedsubmodule 2>/dev/null || true
git submodule update --remote 2>/dev/null || true

true
