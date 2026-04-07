#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo "(1|2)d(3|4)" >a 2>/dev/null || true
mkdir b 2>/dev/null || true
echo "(3|4)" >b/b 2>/dev/null || true
git add a b 2>/dev/null || true
git commit -m "add a and b" 2>/dev/null || true
test_tick 2>/dev/null || true
git init submodule 2>/dev/null || true
echo "(1|2)d(3|4)" >submodule/a 2>/dev/null || true
git submodule add ./submodule 2>/dev/null || true
git commit -m "added submodule" 2>/dev/null || true
test_tick 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true

true
